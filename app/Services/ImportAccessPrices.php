<?php

namespace App\Services;

use App\Models\AccessMigrationBatch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportAccessPrices
{
    private const DEFAULT_PRICE_TABLE = '既定価格記録';

    private const CUSTOMER_PRICE_TABLE = '取引先別価格-商品';

    public function import(AccessMigrationBatch $batch): array
    {
        if (! in_array($batch->status, [
            'masters_imported',
            'shipments_imported',
            'inventory_history_imported',
            'opening_stock_imported',
            'receivables_imported',
            'completed',
            'prices_imported',
        ], true)) {
            throw new RuntimeException("価格移行できないAccess移行バッチ状態です: {$batch->status}");
        }

        return DB::transaction(function () use ($batch): array {
            $context = $this->context($batch);
            $default = $this->importDefaultPrices($batch, $context);
            $customer = $this->importCustomerPrices($batch, $context);

            $summary = [
                'default_price_rules' => $default['imported'],
                'customer_price_rules' => $customer['imported'],
                'skipped_default_price_rows' => $default['skipped'],
                'skipped_customer_price_rows' => $customer['skipped'],
                'imported_at' => now()->toIso8601String(),
            ];

            $validationSummary = $batch->validation_summary ?? [];
            $validationSummary['imports']['prices'] = $summary;
            $batch->update([
                'validation_summary' => $validationSummary,
                'completed_at' => now(),
            ]);

            return $summary;
        }, 3);
    }

    private function context(AccessMigrationBatch $batch): array
    {
        return [
            'products' => $this->sourceTargetMap($batch, '商品マスター', 'products'),
            'customers' => $this->sourceTargetMap($batch, '取引先マスター', 'customers'),
            'price_lists' => DB::table('price_lists')->whereIn('code', [
                'producer_price',
                'wholesale_price',
                'retail_price',
                'customer_price',
            ])->pluck('id', 'code'),
            'transaction_categories' => DB::table('transaction_categories')->whereIn('code', [
                'producer',
                'wholesale',
                'retail',
            ])->pluck('id', 'code'),
            'units' => DB::table('products')->pluck('sales_unit_id', 'id'),
        ];
    }

    private function importDefaultPrices(AccessMigrationBatch $batch, array $context): array
    {
        $rows = $this->sourceRows($batch, self::DEFAULT_PRICE_TABLE)
            ->map(fn (object $row): array => $this->defaultSource($row, $context))
            ->groupBy(fn (array $source): string => "{$source['product_id']}:{$source['transaction_category_id']}");

        $imported = 0;
        $skipped = 0;
        foreach ($rows as $group) {
            $result = $this->upsertTimeline($batch, $group->values(), false);
            $imported += $result['imported'];
            $skipped += $result['skipped'];
        }

        DB::table('access_migration_staging_rows')
            ->where('batch_id', $batch->id)
            ->where('source_table', self::DEFAULT_PRICE_TABLE)
            ->whereNull('target_table')
            ->update(['status' => 'imported', 'updated_at' => now()]);

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    private function importCustomerPrices(AccessMigrationBatch $batch, array $context): array
    {
        $rows = $this->sourceRows($batch, self::CUSTOMER_PRICE_TABLE)
            ->map(fn (object $row): array => $this->customerSource($row, $context))
            ->groupBy(fn (array $source): string => "{$source['customer_id']}:{$source['product_id']}");

        $imported = 0;
        $skipped = 0;
        foreach ($rows as $group) {
            $result = $this->upsertTimeline($batch, $group->values(), true);
            $imported += $result['imported'];
            $skipped += $result['skipped'];
        }

        DB::table('access_migration_staging_rows')
            ->where('batch_id', $batch->id)
            ->where('source_table', self::CUSTOMER_PRICE_TABLE)
            ->whereNull('target_table')
            ->update(['status' => 'imported', 'updated_at' => now()]);

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    private function upsertTimeline(AccessMigrationBatch $batch, Collection $sources, bool $customerSpecific): array
    {
        $sources = $sources
            ->sortBy([['effective_from', 'asc'], ['source_row_number', 'asc']])
            ->groupBy('effective_from')
            ->map(fn (Collection $sameDate): array => $sameDate->last())
            ->values();

        $imported = 0;
        $skipped = 0;
        $now = now();

        foreach ($sources as $index => $source) {
            $next = $sources->get($index + 1);
            $effectiveTo = $next === null
                ? null
                : CarbonImmutable::parse($next['effective_from'])->subDay()->toDateString();

            if ($source['unit_price'] <= 0 || $source['product_id'] === null || $source['unit_id'] === null) {
                $skipped++;
                continue;
            }

            if ($customerSpecific && $source['customer_id'] === null) {
                $skipped++;
                continue;
            }

            if (! $customerSpecific && ($source['price_list_id'] === null || $source['transaction_category_id'] === null)) {
                $skipped++;
                continue;
            }

            $rule = [
                'price_list_id' => $source['price_list_id'],
                'product_id' => $source['product_id'],
                'customer_id' => $source['customer_id'],
                'transaction_category_id' => $source['transaction_category_id'],
                'unit_id' => $source['unit_id'],
                'unit_price' => $source['unit_price'],
                'currency' => 'JPY',
                'priority' => $customerSpecific ? 100 : 200,
                'effective_from' => $source['effective_from'],
                'effective_to' => $effectiveTo,
                'rounding_method' => 'round',
                'reason' => $source['reason'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            DB::table('price_rules')->updateOrInsert(
                [
                    'price_list_id' => $rule['price_list_id'],
                    'product_id' => $rule['product_id'],
                    'customer_id' => $rule['customer_id'],
                    'transaction_category_id' => $rule['transaction_category_id'],
                    'unit_id' => $rule['unit_id'],
                    'effective_from' => $rule['effective_from'],
                ],
                $rule,
            );

            $targetId = DB::table('price_rules')
                ->where('price_list_id', $rule['price_list_id'])
                ->where('product_id', $rule['product_id'])
                ->where('unit_id', $rule['unit_id'])
                ->whereDate('effective_from', $rule['effective_from'])
                ->when($rule['customer_id'] === null, fn ($query) => $query->whereNull('customer_id'), fn ($query) => $query->where('customer_id', $rule['customer_id']))
                ->when($rule['transaction_category_id'] === null, fn ($query) => $query->whereNull('transaction_category_id'), fn ($query) => $query->where('transaction_category_id', $rule['transaction_category_id']))
                ->value('id');

            $this->recordMapping($batch, $source, (int) $targetId);
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    private function defaultSource(object $row, array $context): array
    {
        $payload = $this->payload($row);
        $productId = $context['products']->get((string) ($payload['商品ID'] ?? ''));
        $code = match ($payload['取引区分'] ?? null) {
            '生産者価格' => 'producer',
            '卸価格' => 'wholesale',
            '小売価格' => 'retail',
            default => null,
        };
        $priceListCode = $code === null ? null : "{$code}_price";

        return [
            'row_id' => $row->id,
            'source_table' => $row->source_table,
            'source_key' => (string) $row->source_key,
            'source_row_number' => (int) $row->source_row_number,
            'payload_sha256' => $row->payload_sha256,
            'price_list_id' => $priceListCode === null ? null : $context['price_lists']->get($priceListCode),
            'product_id' => $productId === null ? null : (int) $productId,
            'customer_id' => null,
            'transaction_category_id' => $code === null ? null : $context['transaction_categories']->get($code),
            'unit_id' => $productId === null ? null : $context['units']->get((int) $productId),
            'unit_price' => $this->number($payload['単価'] ?? null),
            'effective_from' => $this->date($payload['年月日'] ?? null, "{$row->source_table}/{$row->source_key}"),
            'reason' => 'Access既定価格記録',
        ];
    }

    private function customerSource(object $row, array $context): array
    {
        $payload = $this->payload($row);
        $productId = $context['products']->get((string) ($payload['商品ID'] ?? ''));

        return [
            'row_id' => $row->id,
            'source_table' => $row->source_table,
            'source_key' => (string) $row->source_key,
            'source_row_number' => (int) $row->source_row_number,
            'payload_sha256' => $row->payload_sha256,
            'price_list_id' => $context['price_lists']->get('customer_price'),
            'product_id' => $productId === null ? null : (int) $productId,
            'customer_id' => $context['customers']->get((string) ($payload['取引先ID'] ?? '')),
            'transaction_category_id' => null,
            'unit_id' => $productId === null ? null : $context['units']->get((int) $productId),
            'unit_price' => $this->number($payload['単価'] ?? null),
            'effective_from' => $this->date($payload['設定日'] ?? null, "{$row->source_table}/{$row->source_key}"),
            'reason' => 'Access取引先別価格',
        ];
    }

    private function recordMapping(AccessMigrationBatch $batch, array $source, int $targetId): void
    {
        DB::table('access_migration_mappings')->updateOrInsert(
            [
                'batch_id' => $batch->id,
                'source_table' => $source['source_table'],
                'source_key' => $source['source_key'],
            ],
            [
                'target_table' => 'price_rules',
                'target_id' => (string) $targetId,
                'action' => 'imported',
                'source_payload_sha256' => $source['payload_sha256'],
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('access_migration_staging_rows')->where('id', $source['row_id'])->update([
            'status' => 'imported',
            'target_table' => 'price_rules',
            'target_id' => (string) $targetId,
            'updated_at' => now(),
        ]);
    }

    private function sourceTargetMap(AccessMigrationBatch $batch, string $sourceTable, string $targetTable): Collection
    {
        return DB::table('access_migration_mappings')
            ->where('batch_id', $batch->id)
            ->where('source_table', $sourceTable)
            ->where('target_table', $targetTable)
            ->pluck('target_id', 'source_key');
    }

    private function sourceRows(AccessMigrationBatch $batch, string $table): Collection
    {
        return DB::table('access_migration_staging_rows')
            ->where('batch_id', $batch->id)
            ->where('source_table', $table)
            ->orderBy('source_row_number')
            ->get();
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

    private function number(mixed $value): float
    {
        return $value === null || $value === '' ? 0.0 : (float) $value;
    }
}
