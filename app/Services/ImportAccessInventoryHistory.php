<?php

namespace App\Services;

use App\Models\AccessMigrationBatch;
use App\Models\LiquorTaxCategory;
use App\Models\Product;
use App\Models\StockLocation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportAccessInventoryHistory
{
    private const SOURCE_TABLE = '伝票外在庫出入';

    private const DETAIL_MAPPING_TABLE = '伝票外在庫出入:商品詳細';

    private const LINE_MAPPING_TABLE = '伝票外在庫出入:明細';

    public function import(AccessMigrationBatch $batch, bool $deltaOnly = false, ?string $cutoverDate = null): array
    {
        if (! in_array($batch->status, ['shipments_imported', 'inventory_history_imported'], true)) {
            throw new RuntimeException("在庫履歴を移行できないバッチ状態です: {$batch->status}");
        }

        $cutoverDate = $cutoverDate === null ? null : CarbonImmutable::parse($cutoverDate)->toDateString();

        return DB::transaction(function () use ($batch, $deltaOnly, $cutoverDate): array {
            $context = $this->context($batch);
            $detailResult = $this->importHistoricalLots($batch, $context, $deltaOnly, $cutoverDate);
            $headerResult = $this->importHeaders($batch, $deltaOnly, $cutoverDate);
            $lineCount = $this->importLines($batch, $context, $deltaOnly, $cutoverDate);
            $this->recordMappings($batch, $detailResult['hashes'], $deltaOnly, $cutoverDate);

            $summary = [
                'production_lots' => $detailResult['count'],
                'operation_headers' => $headerResult['count'],
                'operation_lines' => $lineCount,
                'tax_review_headers' => $headerResult['tax_review_count'],
                'stock_movements_created' => 0,
                'cutover_date' => $cutoverDate,
                'returns_already_imported_as_shipments' => DB::table('shipment_headers')->whereNotNull('legacy_access_document_number')->where('confirmed_liquor_tax_treatment', 'return')->count(),
                'imported_at' => now()->toIso8601String(),
            ];
            $validationSummary = $batch->validation_summary ?? [];
            $validationSummary['imports']['inventory_history'] = $summary;
            $batch->update([
                'status' => 'inventory_history_imported',
                'validation_summary' => $validationSummary,
                'completed_at' => now(),
            ]);

            return $summary;
        }, 3);
    }

    private function context(AccessMigrationBatch $batch): array
    {
        $productIds = DB::table('access_migration_mappings')
            ->where('batch_id', $batch->id)
            ->where('source_table', '商品マスター')
            ->where('target_table', 'products')
            ->pluck('target_id', 'source_key');
        $products = Product::query()
            ->with(['inventoryUnit', 'baseUnit', 'capacityUnit', 'sakeDetail'])
            ->whereIn('id', $productIds->values())
            ->get()->keyBy('id');
        $location = StockLocation::query()->where('code', 'main_brewery')->first()
            ?? StockLocation::query()->where('is_default_shipping_location', true)->first()
            ?? throw new RuntimeException('履歴在庫場所を解決できません。');

        return [
            'product_ids' => $productIds,
            'products' => $products,
            'location' => $location,
            'liquor_categories' => LiquorTaxCategory::query()->get()->keyBy('code'),
        ];
    }

    private function importHistoricalLots(AccessMigrationBatch $batch, array $context, bool $deltaOnly, ?string $cutoverDate): array
    {
        $names = $this->sourceQuery($batch, '商品詳細名称')
            ->get()
            ->mapWithKeys(function ($row): array {
                $source = $this->payload($row);

                return [(string) $row->source_key => [
                    'name' => $this->blankToNull($source['商品詳細名称'] ?? null),
                    'date' => $this->dateOrNull($source['設定日'] ?? null),
                ]];
            });
        $details = [];

        $this->sourceQuery($batch, self::SOURCE_TABLE, $deltaOnly, $cutoverDate)->chunkById(500, function ($rows) use (&$details): void {
            foreach ($rows as $row) {
                $source = $this->payload($row);
                foreach ([['増商品詳細ID', '増商品ID'], ['減商品詳細ID', '減商品ID']] as [$detailField, $productField]) {
                    $detailId = $this->blankToNull($source[$detailField] ?? null);
                    $productId = $this->blankToNull($source[$productField] ?? null);
                    if ($detailId === null || $productId === null) {
                        continue;
                    }
                    if (isset($details[$detailId]) && $details[$detailId] !== $productId) {
                        throw new RuntimeException("Access商品詳細{$detailId}が複数商品に紐づいています。");
                    }
                    $details[$detailId] = $productId;
                }
            }
        });

        $records = [];
        $hashes = [];
        $now = now();
        foreach ($details as $detailId => $sourceProductId) {
            $productId = $context['product_ids']->get((string) $sourceProductId);
            $product = $productId ? $context['products']->get((int) $productId) : null;
            if ($product === null) {
                throw new RuntimeException("Access商品詳細{$detailId}の商品を解決できません。");
            }
            $detail = $names->get((string) $detailId, ['name' => null, 'date' => null]);
            $legacyName = $detail['name'] ?? "名称未登録 詳細ID {$detailId}";
            $unit = $product->inventoryUnit ?? $product->baseUnit;
            $lotCode = 'ITARO-LOT-'.str_pad((string) $detailId, 6, '0', STR_PAD_LEFT);
            $displayName = mb_substr("{$product->display_name} / {$legacyName}", 0, 160);
            $hashes[(string) $detailId] = strtoupper(hash('sha256', "{$detailId}|{$sourceProductId}|{$legacyName}"));
            $records[] = [
                'lot_code' => $lotCode,
                'display_name' => $displayName,
                'status' => 'archived',
                'stock_location_id' => $context['location']->id,
                'unit_id' => $unit->id,
                'capacity_value' => $product->capacity_value,
                'capacity_unit_id' => $product->capacity_unit_id,
                'alcohol_percentage' => $product->alcohol_percentage,
                'analysis_status' => $product->is_alcohol ? 'confirmed' : null,
                'external_system_code' => 'ITARO-DETAIL-'.$detailId,
                'legacy_lot_text' => mb_substr($legacyName, 0, 160),
                'search_key' => "{$lotCode} {$product->product_code} {$product->display_name} {$legacyName}",
                'note' => "Access在庫履歴用ロット; batch={$batch->id}; 商品詳細ID={$detailId}; 商品ID={$sourceProductId}; 設定日=".($detail['date'] ?? ''),
                'is_active' => false,
                'disabled_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($records) === 500) {
                $this->upsertLots($records, $deltaOnly);
                $records = [];
            }
        }
        if ($records !== []) {
            $this->upsertLots($records, $deltaOnly);
        }

        return ['count' => count($details), 'hashes' => $hashes];
    }

    private function upsertLots(array $records, bool $preserveExisting = false): void
    {
        if ($preserveExisting) {
            // A historical delta must not archive or relabel an operational lot.
            DB::table('production_lots')->insertOrIgnore($records);

            return;
        }

        DB::table('production_lots')->upsert(
            $records,
            ['lot_code'],
            array_diff(array_keys($records[0]), ['lot_code', 'created_at']),
        );
    }

    private function importHeaders(AccessMigrationBatch $batch, bool $deltaOnly, ?string $cutoverDate): array
    {
        $count = 0;
        $taxReviewCount = 0;
        $now = now();

        $this->sourceQuery($batch, self::SOURCE_TABLE, $deltaOnly, $cutoverDate)->chunkById(500, function ($rows) use ($batch, &$count, &$taxReviewCount, $now): void {
            $records = [];
            foreach ($rows as $row) {
                $source = $this->payload($row);
                $sourceId = (string) ($source['ID'] ?? $row->source_key);
                $typeId = (int) ($source['伝票外在庫出入ID'] ?? 0);
                [$operationType, $label, $taxTreatment] = $this->operationType($typeId);
                $records[] = [
                    'operation_number' => 'ITARO-NSS-'.$sourceId,
                    'revision_no' => 1,
                    'status' => 'confirmed',
                    'operation_type' => $operationType,
                    'operation_date' => $this->date($source['年月日'] ?? null, "伝票外在庫出入{$sourceId}"),
                    'liquor_tax_treatment' => $taxTreatment,
                    'requires_tax_review' => $taxTreatment === 'review',
                    'confirmed_at' => $now,
                    'reason' => "Access伝票外在庫出入: {$label}",
                    'note' => implode('; ', array_filter([
                        "Access移行 batch={$batch->id}",
                        "区分ID={$typeId}",
                        $this->blankToNull($source['摘要'] ?? null),
                    ])),
                    'legacy_access_stock_operation_id' => $sourceId,
                    'legacy_access_volume_delta_ml' => $this->number($source['増減数'] ?? null),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $count++;
                $taxReviewCount += $taxTreatment === 'review' ? 1 : 0;
            }
            DB::table('non_sales_stock_operation_headers')->upsert(
                $records,
                ['legacy_access_stock_operation_id'],
                array_diff(array_keys($records[0]), ['legacy_access_stock_operation_id', 'created_at']),
            );
        });

        return ['count' => $count, 'tax_review_count' => $taxReviewCount];
    }

    private function importLines(AccessMigrationBatch $batch, array $context, bool $deltaOnly, ?string $cutoverDate): int
    {
        $headers = DB::table('non_sales_stock_operation_headers')->whereNotNull('legacy_access_stock_operation_id')->pluck('id', 'legacy_access_stock_operation_id');
        $lots = DB::table('production_lots')->where('external_system_code', 'like', 'ITARO-DETAIL-%')->pluck('id', 'external_system_code');
        $count = 0;
        $now = now();

        $this->sourceQuery($batch, self::SOURCE_TABLE, $deltaOnly, $cutoverDate)->chunkById(500, function ($rows) use ($context, $headers, $lots, &$count, $now): void {
            $records = [];
            foreach ($rows as $row) {
                $source = $this->payload($row);
                $sourceId = (string) ($source['ID'] ?? $row->source_key);
                $headerId = $headers->get($sourceId)
                    ?? throw new RuntimeException("Access在庫出入{$sourceId}のヘッダーを解決できません。");
                $typeId = (int) ($source['伝票外在庫出入ID'] ?? 0);
                [, $label, $taxTreatment] = $this->operationType($typeId);
                $lineNo = 0;
                foreach ([
                    ['side' => 'plus', 'product' => '増商品ID', 'detail' => '増商品詳細ID', 'quantity' => '増個数', 'tax' => '増商品酒税', 'payable' => '増商品軽減率', 'sign' => 1],
                    ['side' => 'minus', 'product' => '減商品ID', 'detail' => '減商品詳細ID', 'quantity' => '減個数', 'tax' => '減商品酒税', 'payable' => '減商品軽減率', 'sign' => -1],
                ] as $leg) {
                    $sourceProductId = $this->blankToNull($source[$leg['product']] ?? null);
                    $detailId = $this->blankToNull($source[$leg['detail']] ?? null);
                    if ($sourceProductId === null || $detailId === null) {
                        continue;
                    }
                    $productId = $context['product_ids']->get($sourceProductId);
                    $product = $productId ? $context['products']->get((int) $productId) : null;
                    $lotId = $lots->get('ITARO-DETAIL-'.$detailId);
                    if ($product === null || $lotId === null) {
                        throw new RuntimeException("Access在庫出入{$sourceId}の{$leg['side']}側商品・ロットを解決できません。");
                    }
                    $lineNo++;
                    $unit = $product->inventoryUnit ?? $product->baseUnit;
                    $quantity = abs((float) ($source[$leg['quantity']] ?? 0)) * $leg['sign'];
                    $taxPerKl = $this->number($source[$leg['tax']] ?? null);
                    $payableRate = $this->number($source[$leg['payable']] ?? null);
                    $reductionRate = $payableRate === null ? 0.0 : max(0.0, min(1.0, 1 - ($payableRate / 100)));
                    $liquorCategory = $product->is_alcohol
                        ? $context['liquor_categories']->get($product->sakeDetail?->liquor_tax_category_code ?? 'seishu')
                        : null;
                    $taxableKl = $product->is_alcohol ? abs($this->taxableKl($quantity, $product->capacity_value, $product->capacityUnit?->code)) : 0.0;
                    $estimatedTax = $taxPerKl === null ? null : round($taxableKl * $taxPerKl * (1 - $reductionRate), 2);
                    $records[] = [
                        'non_sales_stock_operation_header_id' => $headerId,
                        'line_no' => $lineNo,
                        'stock_movement_id' => null,
                        'product_id' => $product->id,
                        'stock_location_id' => $context['location']->id,
                        'unit_id' => $unit->id,
                        'quantity' => $quantity,
                        'production_lot_id' => $lotId,
                        'lot_code' => 'ITARO-LOT-'.str_pad($detailId, 6, '0', STR_PAD_LEFT),
                        'reason' => $label,
                        'note' => "Access履歴; 商品詳細ID={$detailId}; 側={$leg['side']}; 在庫移動未生成",
                        'liquor_tax_category_id' => $liquorCategory?->id,
                        'liquor_tax_category_code' => $liquorCategory?->code,
                        'liquor_tax_category_name' => $liquorCategory?->name,
                        'liquor_taxability' => $liquorCategory?->taxability,
                        'liquor_tax_rule_id' => null,
                        'liquor_tax_calculation_method' => $liquorCategory ? 'fixed_per_kl' : null,
                        'liquor_taxable_kl' => $liquorCategory ? $taxableKl : 0,
                        'liquor_tax_per_kl' => $liquorCategory ? $taxPerKl : null,
                        'liquor_tax_reduction_rate' => $liquorCategory ? $reductionRate : null,
                        'liquor_tax_estimated_amount' => $liquorCategory && $taxTreatment === 'review' ? $estimatedTax : 0,
                        'legacy_access_stock_leg_key' => $sourceId.':'.$leg['side'],
                        'legacy_access_detail_id' => $detailId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $count++;
                }
            }
            DB::table('non_sales_stock_operation_lines')->upsert(
                $records,
                ['legacy_access_stock_leg_key'],
                array_diff(array_keys($records[0]), ['legacy_access_stock_leg_key', 'created_at']),
            );
        });

        return $count;
    }

    private function recordMappings(AccessMigrationBatch $batch, array $detailHashes, bool $deltaOnly, ?string $cutoverDate): void
    {
        $sourceHashes = $this->sourceQuery($batch, self::SOURCE_TABLE, $deltaOnly, $cutoverDate)->pluck('payload_sha256', 'source_key');
        $this->recordTargetMappings($batch, self::SOURCE_TABLE, 'non_sales_stock_operation_headers', 'legacy_access_stock_operation_id', $sourceHashes);
        $lineHashes = $sourceHashes->flatMap(fn (string $hash, string $key): array => [
            $key.':plus' => $hash,
            $key.':minus' => $hash,
        ]);
        $this->recordTargetMappings($batch, self::LINE_MAPPING_TABLE, 'non_sales_stock_operation_lines', 'legacy_access_stock_leg_key', $lineHashes);

        $lots = DB::table('production_lots')
            ->where('external_system_code', 'like', 'ITARO-DETAIL-%')
            ->whereIn(
                'external_system_code',
                array_map(fn (string $id): string => 'ITARO-DETAIL-'.$id, array_keys($detailHashes)),
            )
            ->get(['id', 'external_system_code']);
        $records = [];
        $now = now();
        foreach ($lots as $lot) {
            $detailId = substr((string) $lot->external_system_code, strlen('ITARO-DETAIL-'));
            $records[] = [
                'batch_id' => $batch->id,
                'source_table' => self::DETAIL_MAPPING_TABLE,
                'source_key' => $detailId,
                'target_table' => 'production_lots',
                'target_id' => (string) $lot->id,
                'action' => 'imported',
                'source_payload_sha256' => $detailHashes[$detailId],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($records, 500) as $chunk) {
            DB::table('access_migration_mappings')->upsert($chunk, ['batch_id', 'source_table', 'source_key'], ['target_table', 'target_id', 'action', 'source_payload_sha256', 'updated_at']);
        }
        $this->sourceQuery($batch, self::SOURCE_TABLE, $deltaOnly, $cutoverDate)->update([
            'status' => 'imported', 'target_table' => 'non_sales_stock_operation_headers', 'updated_at' => now(),
        ]);
    }

    private function recordTargetMappings(AccessMigrationBatch $batch, string $sourceTable, string $targetTable, string $legacyColumn, $sourceHashes = null): void
    {
        DB::table($targetTable)
            ->whereNotNull($legacyColumn)
            ->when($sourceHashes !== null, fn ($query) => $query->whereIn($legacyColumn, $sourceHashes->keys()))
            ->orderBy('id')
            ->chunkById(500, function ($targets) use ($batch, $sourceTable, $targetTable, $legacyColumn, $sourceHashes): void {
                $now = now();
                $records = [];
                foreach ($targets as $target) {
                    $key = (string) $target->{$legacyColumn};
                    $hash = $sourceHashes?->get($key) ?? strtoupper(hash('sha256', $key));
                    $records[] = [
                        'batch_id' => $batch->id, 'source_table' => $sourceTable, 'source_key' => $key,
                        'target_table' => $targetTable, 'target_id' => (string) $target->id, 'action' => 'imported',
                        'source_payload_sha256' => $hash, 'created_at' => $now, 'updated_at' => $now,
                    ];
                }
                DB::table('access_migration_mappings')->upsert($records, ['batch_id', 'source_table', 'source_key'], ['target_table', 'target_id', 'action', 'source_payload_sha256', 'updated_at']);
            });
    }

    private function operationType(int $typeId): array
    {
        return match ($typeId) {
            1 => ['bottling', '瓶詰', 'not_applicable'],
            2 => ['repackaging', '詰替', 'not_applicable'],
            3 => ['breakage', '破損', 'review'],
            4 => ['adjustment', '庫内戻', 'not_applicable'],
            5 => ['disposal', '収去（廃棄）', 'review'],
            7 => ['return_to_manufacturing', '移入', 'return'],
            8 => ['loss', '被災', 'review'],
            default => throw new RuntimeException("未対応のAccess伝票外在庫出入区分です: {$typeId}"),
        };
    }

    private function sourceQuery(AccessMigrationBatch $batch, string $table, bool $deltaOnly = false, ?string $cutoverDate = null)
    {
        return DB::table('access_migration_staging_rows')
            ->where('batch_id', $batch->id)
            ->where('source_table', $table)
            ->when($cutoverDate !== null && $table === self::SOURCE_TABLE, fn ($query) => $query
                ->whereRaw("CAST(payload->>'年月日' AS date) >= ?", [$cutoverDate]))
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

    private function date(mixed $value, string $label): string
    {
        try {
            return CarbonImmutable::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            throw new RuntimeException("{$label}の日付を解釈できません: ".(string) $value);
        }
    }

    private function dateOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value)->toDateString();
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

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
