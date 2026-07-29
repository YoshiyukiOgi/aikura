<?php

namespace App\Services;

use App\Models\AccessMigrationBatch;
use App\Models\Customer;
use App\Models\LiquorTaxCategory;
use App\Models\Product;
use App\Models\SettlementReceivableCategory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportAccessShipments
{
    private const HEADER_TABLE = '出荷伝票・取引先';

    private const LINE_TABLE = '出荷伝票・商品';

    public function import(AccessMigrationBatch $batch, bool $deltaOnly = false): array
    {
        if (! in_array($batch->status, ['masters_imported', 'shipments_imported'], true)) {
            throw new RuntimeException("出荷履歴を移行できないバッチ状態です: {$batch->status}");
        }

        return DB::transaction(function () use ($batch, $deltaOnly): array {
            $context = $this->context($batch);
            $headerResult = $this->importHeaders($batch, $context, $deltaOnly);
            $lineCount = $this->importLines($batch, $context, $deltaOnly);
            $this->recordMappings($batch, $deltaOnly);

            $summary = [
                'shipment_headers' => $headerResult['count'],
                'shipment_lines' => $lineCount,
                'tax_review_headers' => $headerResult['tax_review_count'],
                'stock_movements_created' => 0,
                'imported_at' => now()->toIso8601String(),
            ];
            $validationSummary = $batch->validation_summary ?? [];
            $validationSummary['imports']['shipments'] = $summary;
            $batch->update([
                'status' => 'shipments_imported',
                'validation_summary' => $validationSummary,
                'completed_at' => now(),
            ]);

            return $summary;
        }, 3);
    }

    private function context(AccessMigrationBatch $batch): array
    {
        $customerIds = $this->sourceTargetMap($batch, '取引先マスター', 'customers');
        $productIds = $this->sourceTargetMap($batch, '商品マスター', 'products');
        $customers = Customer::query()
            ->with(['settlementReceivableCategory', 'transactionCategory', 'billingCycle'])
            ->whereIn('id', $customerIds->values())
            ->get()->keyBy('id');
        $products = Product::query()
            ->with(['salesUnit', 'baseUnit', 'capacityUnit', 'consumptionTaxCategory', 'sakeDetail'])
            ->whereIn('id', $productIds->values())
            ->get()->keyBy('id');

        return [
            'customer_ids' => $customerIds,
            'product_ids' => $productIds,
            'customers' => $customers,
            'products' => $products,
            'settlements' => SettlementReceivableCategory::query()->get()->keyBy('code'),
            'liquor_categories' => LiquorTaxCategory::query()->get()->keyBy('code'),
        ];
    }

    private function importHeaders(AccessMigrationBatch $batch, array $context, bool $deltaOnly): array
    {
        $count = 0;
        $taxReviewCount = 0;
        $now = now();

        $this->sourceQuery($batch, self::HEADER_TABLE, $deltaOnly)->chunkById(500, function ($rows) use ($batch, $context, &$count, &$taxReviewCount, $now): void {
            $records = [];
            foreach ($rows as $row) {
                $source = $this->payload($row);
                $sourceDocument = (string) ($source['伝票番号'] ?? $row->source_key);
                $customerSourceId = (string) ($source['取引先ID'] ?? '');
                $customerId = $context['customer_ids']->get($customerSourceId);
                $customer = $customerId ? $context['customers']->get((int) $customerId) : null;
                if ($customer === null) {
                    throw new RuntimeException("出荷伝票{$sourceDocument}の得意先を解決できません。");
                }

                $class = (int) ($source['酒税区分'] ?? 1);
                $settlementCode = match ($class) {
                    5 => 'untaxed_transfer',
                    6 => 'export',
                    default => $customer->settlementReceivableCategory->code,
                };
                $settlement = $context['settlements']->get($settlementCode)
                    ?? throw new RuntimeException("決算売掛区分を解決できません: {$settlementCode}");
                $treatment = match ($class) {
                    2 => 'return',
                    5 => 'untaxed_transfer',
                    6 => 'export_exempt',
                    default => 'taxable',
                };
                $requiresReview = $this->hasTaxFlagMismatch($source, $class);
                $date = $this->date($source['年月日'] ?? null, "出荷伝票{$sourceDocument}");
                $issuedAt = $this->dateTime($source['入力日時'] ?? null) ?? $date.' 00:00:00';

                $records[] = [
                    'document_number' => 'ITARO-S-'.$sourceDocument,
                    'status' => 'confirmed',
                    'customer_id' => $customer->id,
                    'transaction_category_id' => $customer->transaction_category_id,
                    'settlement_receivable_category_id' => $settlement->id,
                    'billing_cycle_id' => $customer->billing_cycle_id,
                    'document_date' => $date,
                    'document_issued_at' => $issuedAt,
                    'order_date' => null,
                    'scheduled_shipment_date' => $date,
                    'actual_shipment_date' => $date,
                    'sales_recorded_on' => $date,
                    'billing_target_date' => $date,
                    'liquor_tax_transfer_date' => $date,
                    'confirmed_settlement_receivable_category_id' => $settlement->id,
                    'confirmed_settlement_receivable_category_code' => $settlement->code,
                    'confirmed_settlement_receivable_category_name' => $settlement->name,
                    'confirmed_liquor_tax_treatment' => $treatment,
                    'confirmed_consumption_tax_treatment' => $class === 6 ? 'export_exempt' : ((bool) ($source['消費税未納取引'] ?? false) ? 'non_taxable' : 'taxable'),
                    'confirmed_export_type' => $settlement->export_type,
                    'confirmed_receivable_method' => $settlement->receivable_method,
                    'confirmed_invoice_required' => $settlement->invoice_required,
                    'confirmed_requires_tax_review' => $requiresReview,
                    'confirmed_requires_evidence' => false,
                    'note' => $this->headerNote($batch, $class, $source),
                    'legacy_access_document_number' => $sourceDocument,
                    'legacy_access_net_amount' => $this->number($source['金額'] ?? null),
                    'legacy_access_consumption_tax_amount' => $this->number($source['消費税額'] ?? null),
                    'legacy_access_total_amount' => $this->number($source['合計'] ?? null),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $count++;
                $taxReviewCount += $requiresReview ? 1 : 0;
            }

            DB::table('shipment_headers')->upsert($records, ['legacy_access_document_number'], array_diff(array_keys($records[0]), ['created_at', 'legacy_access_document_number']));
        });

        return ['count' => $count, 'tax_review_count' => $taxReviewCount];
    }

    private function importLines(AccessMigrationBatch $batch, array $context, bool $deltaOnly): int
    {
        $headers = DB::table('shipment_headers')->whereNotNull('legacy_access_document_number')->pluck('id', 'legacy_access_document_number');
        $liquorTreatments = DB::table('shipment_headers')->whereNotNull('legacy_access_document_number')->pluck('confirmed_liquor_tax_treatment', 'legacy_access_document_number');
        $consumptionTreatments = DB::table('shipment_headers')->whereNotNull('legacy_access_document_number')->pluck('confirmed_consumption_tax_treatment', 'legacy_access_document_number');
        $lineNumbers = $deltaOnly
            ? DB::table('shipment_headers')
                ->join('shipment_lines', 'shipment_lines.shipment_header_id', '=', 'shipment_headers.id')
                ->whereNotNull('shipment_headers.legacy_access_document_number')
                ->groupBy('shipment_headers.legacy_access_document_number')
                ->pluck(DB::raw('MAX(shipment_lines.line_no)'), 'shipment_headers.legacy_access_document_number')
                ->map(fn ($value): int => (int) $value)
                ->all()
            : [];
        $sourceLineNumbersByDocument = [];
        $sourceLineKeysByDocument = [];
        $count = 0;
        $now = now();

        $this->sourceQuery($batch, self::LINE_TABLE, $deltaOnly)->chunkById(500, function ($rows) use ($context, $headers, $liquorTreatments, $consumptionTreatments, &$lineNumbers, &$sourceLineNumbersByDocument, &$sourceLineKeysByDocument, &$count, $now, $deltaOnly): void {
            $records = [];
            foreach ($rows as $row) {
                $source = $this->payload($row);
                $sourceDocument = (string) ($source['伝票番号'] ?? '');
                $headerId = $headers->get($sourceDocument);
                $productSourceId = (string) ($source['商品ID'] ?? '');
                $productId = $context['product_ids']->get($productSourceId);
                $product = $productId ? $context['products']->get((int) $productId) : null;
                if ($headerId === null || $product === null) {
                    throw new RuntimeException("出荷明細{$row->source_key}の伝票または商品を解決できません。");
                }

                $lineNumbers[$sourceDocument] = ($lineNumbers[$sourceDocument] ?? 0) + 1;
                $sourceLineNumbersByDocument[$sourceDocument][] = $lineNumbers[$sourceDocument];
                $sourceLineKeysByDocument[$sourceDocument][] = (string) $row->source_key;
                $unit = $product->salesUnit ?? $product->baseUnit;
                $taxCategory = $product->consumptionTaxCategory;
                $quantity = (float) ($source['個数'] ?? 0);
                $rate = ((float) ($source['消費税率'] ?? 0)) / 100;
                $taxPerKl = $this->number($source['酒税'] ?? null);
                $payableRate = $this->nullableFloat($source['軽減率'] ?? null);
                $reductionRate = $payableRate === null ? 0.0 : max(0.0, min(1.0, 1 - ($payableRate / 100)));
                $liquorCategory = $product->is_alcohol
                    ? $context['liquor_categories']->get($product->sakeDetail?->liquor_tax_category_code ?? 'seishu')
                    : null;
                $taxableKl = $product->is_alcohol ? $this->taxableKl($quantity, $product->capacity_value, $product->capacityUnit?->code) : 0.0;
                $liquorTreatment = $liquorTreatments->get($sourceDocument);
                $consumptionTreatment = $consumptionTreatments->get($sourceDocument);
                $estimatedTax = $taxPerKl === null ? null : match ($liquorTreatment) {
                    'export_exempt', 'untaxed_transfer' => 0.0,
                    default => round($taxableKl * $taxPerKl * (1 - $reductionRate), 2),
                };
                $consumptionTaxability = match ($consumptionTreatment) {
                    'export_exempt' => 'export_exempt',
                    'non_taxable' => 'non_taxable',
                    default => $rate > 0 ? $taxCategory->taxability : 'exempt',
                };

                $records[] = [
                    'shipment_header_id' => $headerId,
                    'line_no' => $lineNumbers[$sourceDocument],
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_id' => $unit->id,
                    'draft_unit_price' => $this->number($source['単価'] ?? null),
                    'draft_price_source' => 'access_history',
                    'confirmed_product_code' => $product->product_code,
                    'confirmed_product_name' => $product->name,
                    'confirmed_display_name' => $product->display_name,
                    'confirmed_product_type' => $product->product_type,
                    'confirmed_unit_code' => $unit->code,
                    'confirmed_unit_name' => $unit->name,
                    'confirmed_quantity' => $quantity,
                    'confirmed_unit_price' => $this->number($source['単価'] ?? null) ?? 0,
                    'confirmed_price_source' => 'access_history',
                    'confirmed_price_reason' => 'Access出荷履歴の保存単価',
                    'confirmed_capacity_value' => $product->capacity_value,
                    'confirmed_capacity_unit_id' => $product->capacity_unit_id,
                    'confirmed_alcohol_percentage' => $product->alcohol_percentage,
                    'confirmed_rounding_method' => 'legacy_saved',
                    'confirmed_consumption_tax_category_id' => $taxCategory->id,
                    'confirmed_consumption_tax_category_code' => $taxCategory->code,
                    'confirmed_consumption_tax_category_name' => $taxCategory->name,
                    'confirmed_consumption_taxability' => $consumptionTaxability,
                    'confirmed_consumption_tax_rate_id' => null,
                    'confirmed_consumption_tax_rate' => $rate,
                    'confirmed_consumption_tax_rate_effective_from' => null,
                    'confirmed_liquor_tax_category_id' => $liquorCategory?->id,
                    'confirmed_liquor_tax_category_code' => $liquorCategory?->code,
                    'confirmed_liquor_tax_category_name' => $liquorCategory?->name,
                    'confirmed_liquor_taxability' => $liquorCategory?->taxability,
                    'confirmed_liquor_tax_rule_id' => null,
                    'confirmed_liquor_tax_calculation_method' => $liquorCategory ? 'fixed_per_kl' : null,
                    'confirmed_liquor_taxable_kl' => $liquorCategory ? $taxableKl : 0,
                    'confirmed_liquor_tax_per_kl' => $liquorCategory ? $taxPerKl : null,
                    'confirmed_liquor_tax_reduction_rate' => $liquorCategory ? $reductionRate : null,
                    'confirmed_liquor_tax_estimated_amount' => $liquorCategory ? $estimatedTax : 0,
                    'confirmed_at' => $now,
                    'note' => $this->lineNote($source),
                    'legacy_access_line_id' => (string) $row->source_key,
                    'legacy_access_detail_id' => $this->blankToNull($source['商品詳細ID'] ?? null),
                    'legacy_access_transaction_amount' => $this->number($source['取引額'] ?? null),
                    'legacy_access_consumption_tax_amount' => $this->number($source['商品税額'] ?? null),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $count++;
            }

            $uniqueBy = $deltaOnly ? ['legacy_access_line_id'] : ['shipment_header_id', 'line_no'];
            DB::table('shipment_lines')->upsert($records, $uniqueBy, array_diff(array_keys($records[0]), ['created_at', ...$uniqueBy]));
        });

        if (! $deltaOnly) {
            $this->cleanupObsoleteLines($headers, $sourceLineNumbersByDocument, $sourceLineKeysByDocument);
        }

        return $count;
    }

    private function cleanupObsoleteLines($headers, array $sourceLineNumbersByDocument, array $sourceLineKeysByDocument): void
    {
        $referenceColumns = [
            'invoice_lines' => 'shipment_line_id',
            'stock_movements' => 'source_shipment_line_id',
            'sales_return_lines' => 'source_shipment_line_id',
            'shipment_lot_allocations' => 'shipment_line_id',
        ];

        foreach ($headers as $sourceDocument => $headerId) {
            $lineNumbers = array_values(array_unique($sourceLineNumbersByDocument[$sourceDocument] ?? []));
            if ($lineNumbers === []) {
                continue;
            }
            $lineKeys = array_values(array_unique($sourceLineKeysByDocument[$sourceDocument] ?? []));

            $candidates = DB::table('shipment_lines')
                ->where('shipment_header_id', $headerId)
                ->whereNotIn('line_no', $lineNumbers)
                ->whereNotIn('legacy_access_line_id', $lineKeys)
                ->pluck('id');

            if ($candidates->isEmpty()) {
                continue;
            }

            $referencedIds = collect();
            foreach ($referenceColumns as $table => $column) {
                $referencedIds = $referencedIds->merge(
                    DB::table($table)->whereIn($column, $candidates)->pluck($column)
                );
            }

            $deletableIds = $candidates->diff($referencedIds)->values();
            if ($deletableIds->isNotEmpty()) {
                DB::table('shipment_lines')->whereIn('id', $deletableIds)->delete();
            }
        }
    }

    private function recordMappings(AccessMigrationBatch $batch, bool $deltaOnly): void
    {
        foreach ([self::HEADER_TABLE => ['shipment_headers', 'legacy_access_document_number'], self::LINE_TABLE => ['shipment_lines', 'legacy_access_line_id']] as $sourceTable => [$targetTable, $legacyColumn]) {
            $this->sourceQuery($batch, $sourceTable, $deltaOnly)->chunkById(500, function ($rows) use ($batch, $sourceTable, $targetTable, $legacyColumn): void {
                $now = now();
                $records = [];
                $sourceKeys = $rows->map(fn ($row) => (string) $row->source_key);
                $targets = DB::table($targetTable)->whereIn($legacyColumn, $sourceKeys)->pluck('id', $legacyColumn);
                foreach ($rows as $row) {
                    $targetId = $targets->get((string) $row->source_key);
                    if ($targetId === null) {
                        throw new RuntimeException("移行先対応を作成できません: {$sourceTable}/{$row->source_key}");
                    }
                    $records[] = [
                        'batch_id' => $batch->id,
                        'source_table' => $sourceTable,
                        'source_key' => (string) $row->source_key,
                        'target_table' => $targetTable,
                        'target_id' => (string) $targetId,
                        'action' => 'imported',
                        'source_payload_sha256' => $row->payload_sha256,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                DB::table('access_migration_mappings')->upsert($records, ['batch_id', 'source_table', 'source_key'], ['target_table', 'target_id', 'action', 'source_payload_sha256', 'updated_at']);
            });
            $this->sourceQuery($batch, $sourceTable, $deltaOnly)->update(['status' => 'imported', 'target_table' => $targetTable, 'updated_at' => now()]);
        }
    }

    private function sourceTargetMap(AccessMigrationBatch $batch, string $sourceTable, string $targetTable)
    {
        return DB::table('access_migration_mappings')
            ->where('batch_id', $batch->id)->where('source_table', $sourceTable)->where('target_table', $targetTable)
            ->pluck('target_id', 'source_key');
    }

    private function sourceQuery(AccessMigrationBatch $batch, string $table, bool $deltaOnly = false)
    {
        return DB::table('access_migration_staging_rows')
            ->where('batch_id', $batch->id)
            ->where('source_table', $table)
            ->when($deltaOnly, fn ($query) => $query->whereExists(function ($delta) use ($batch): void {
                $delta->selectRaw('1')
                    ->from('access_migration_deltas')
                    ->whereColumn('access_migration_deltas.current_staging_row_id', 'access_migration_staging_rows.id')
                    ->where('access_migration_deltas.batch_id', $batch->id)
                    ->where('access_migration_deltas.change_type', 'new');
            }))
            ->orderBy('id');
    }

    private function payload(object $row): array
    {
        return is_array($row->payload) ? $row->payload : json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR);
    }

    private function hasTaxFlagMismatch(array $source, int $class): bool
    {
        return (bool) ($source['戻入取引'] ?? false) !== ($class === 2)
            || (bool) ($source['酒税未納取引'] ?? false) !== ($class === 5)
            || (bool) ($source['輸出取引'] ?? false) !== ($class === 6);
    }

    private function headerNote(AccessMigrationBatch $batch, int $class, array $source): string
    {
        return implode('; ', array_filter([
            "Access移行 batch={$batch->id}",
            "酒税区分={$class}",
            $this->blankToNull($source['摘要'] ?? null),
        ]));
    }

    private function lineNote(array $source): ?string
    {
        return implode('; ', array_filter([
            $this->blankToNull($source['摘要'] ?? null),
            ($detail = $this->blankToNull($source['商品詳細ID'] ?? null)) ? "Access商品詳細ID={$detail}" : null,
        ])) ?: null;
    }

    private function date(mixed $value, string $label): string
    {
        try {
            return CarbonImmutable::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            throw new RuntimeException("{$label}の日付を解釈できません: ".(string) $value);
        }
    }

    private function dateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function taxableKl(float $quantity, mixed $capacity, ?string $unitCode): float
    {
        $liters = match ($unitCode) {
            'milliliter' => (float) $capacity / 1000,
            'liter' => (float) $capacity,
            default => 0,
        };

        return round($quantity * $liters / 1000, 6);
    }

    private function number(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    private function nullableFloat(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
