<?php

namespace App\Services;

use App\Models\AccessMigrationBatch;
use App\Models\BillingCycle;
use App\Models\ConsumptionTaxCategory;
use App\Models\Customer;
use App\Models\FoodProductDetail;
use App\Models\GoodsProductDetail;
use App\Models\KasuProductDetail;
use App\Models\Product;
use App\Models\SakeProductDetail;
use App\Models\SettlementReceivableCategory;
use App\Models\TransactionCategory;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportAccessMasters
{
    public function __construct(private readonly AccessProductNameFormatter $productNameFormatter) {}

    public function import(AccessMigrationBatch $batch): array
    {
        if (! in_array($batch->status, ['ready', 'ready_with_warnings', 'masters_imported'], true)) {
            throw new RuntimeException("マスタ移行できないバッチ状態です: {$batch->status}");
        }

        return DB::transaction(function () use ($batch): array {
            $this->ensureLegacySupportMasters($batch);
            $customerResult = $this->importCustomers($batch);
            $productCount = $this->importProducts($batch);
            $summary = [
                'customers' => $customerResult['count'],
                'customer_name_fallbacks' => $customerResult['name_fallbacks'],
                'products' => $productCount,
                'mappings' => DB::table('access_migration_mappings')->where('batch_id', $batch->id)->count(),
                'imported_at' => now()->toIso8601String(),
            ];

            $validationSummary = $batch->validation_summary ?? [];
            $validationSummary['imports']['masters'] = $summary;
            $batch->update([
                'status' => 'masters_imported',
                'validation_summary' => $validationSummary,
                'completed_at' => now(),
            ]);

            return $summary;
        });
    }

    private function ensureLegacySupportMasters(AccessMigrationBatch $batch): void
    {
        TransactionCategory::query()->updateOrCreate(
            ['code' => 'legacy_other'],
            [
                'name' => 'その他（Access移行）',
                'description' => "Access移行バッチ{$batch->id}の取引区分「その他」を原値のまま保持する区分。",
                'sort_order' => 90,
                'is_active' => true,
            ],
        );

        $closingDays = $this->sourceRows($batch, '取引先マスター')
            ->map(fn ($row) => (int) (($this->payload($row)['閉め日'] ?? null) ?: 31))
            ->unique();
        foreach ($closingDays as $closingDay) {
            if ($closingDay === 31) {
                continue;
            }
            BillingCycle::query()->updateOrCreate(
                ['code' => "legacy_close_{$closingDay}"],
                [
                    'name' => "{$closingDay}日締 翌月末入金（Access移行）",
                    'closing_day' => $closingDay,
                    'payment_month_offset' => 1,
                    'payment_day' => 31,
                    'billing_method' => 'monthly_closing',
                    'description' => "Accessの閉め日{$closingDay}を移行。入金条件は既定の翌月末として保持。",
                    'is_active' => true,
                ],
            );
        }
    }

    private function importCustomers(AccessMigrationBatch $batch): array
    {
        $transactionCategories = TransactionCategory::query()->pluck('id', 'code');
        $settlementCategories = SettlementReceivableCategory::query()->get()->keyBy('code');
        $billingCycles = BillingCycle::query()->pluck('id', 'code');
        $count = 0;
        $nameFallbacks = 0;

        foreach ($this->sourceRows($batch, '取引先マスター') as $row) {
            $source = $this->payload($row);
            $sourceKey = (string) $row->source_key;
            $transactionCode = match ($source['取引区分'] ?? null) {
                '生産者価格' => 'producer',
                '卸価格' => 'wholesale',
                '小売価格' => 'retail',
                default => 'legacy_other',
            };
            $settlementCode = match (true) {
                ($source['業種区分'] ?? null) === '自家用' => 'self_consumption',
                ($source['業種区分'] ?? null) === '蔵置所' => 'tax_paid_storage_destination',
                ($source['業種区分'] ?? null) === '輸出' => 'export',
                (bool) ($source['酒税未納取引'] ?? false) => 'untaxed_transfer',
                default => 'accounts_receivable_1',
            };
            $settlement = $settlementCategories->get($settlementCode)
                ?? throw new RuntimeException("精算売掛区分がありません: {$settlementCode}");
            $closingDay = (int) (($source['閉め日'] ?? null) ?: 31);
            $billingCode = $closingDay === 31 ? 'monthly_end_next_month_end' : "legacy_close_{$closingDay}";
            $originalName = $this->nullIfBlank($source['取引先名'] ?? null);
            $name = $originalName
                ?? $this->nullIfBlank($source['取引先略称'] ?? null)
                ?? $this->nullIfBlank($source['取引先カナ名'] ?? null)
                ?? "名称未設定 {$sourceKey}";
            if ($originalName === null) {
                $nameFallbacks++;
            }

            $customerCode = 'ITARO-C-'.str_pad($sourceKey, 4, '0', STR_PAD_LEFT);
            $customer = Customer::query()->updateOrCreate(
                ['customer_code' => $customerCode],
                [
                    'name' => $name,
                    'name_kana' => $this->nullIfBlank($source['取引先カナ名'] ?? null),
                    'short_name' => $this->nullIfBlank($source['取引先略称'] ?? null),
                    'billing_name' => $name,
                    'postal_code' => $this->nullIfBlank($source['郵便番号1'] ?? null),
                    'address1' => $this->joinText($source['都道府県１'] ?? null, $source['住所1'] ?? null),
                    'address2' => null,
                    'phone' => $this->nullIfBlank($source['電話番号1'] ?? null),
                    'fax' => $this->nullIfBlank($source['ファックス番号1'] ?? null),
                    'email' => null,
                    'contact_name' => $this->nullIfBlank($source['担当者'] ?? null),
                    'transaction_category_id' => $transactionCategories->get($transactionCode)
                        ?? throw new RuntimeException("取引区分がありません: {$transactionCode}"),
                    'settlement_receivable_category_id' => $settlement->id,
                    'billing_cycle_id' => $billingCycles->get($billingCode)
                        ?? throw new RuntimeException("請求締区分がありません: {$billingCode}"),
                    'tax_rounding_method' => 'ceil',
                    'tax_calculation_unit' => 'invoice',
                    'amount_rounding_method' => 'round',
                    'invoice_required' => $settlement->invoice_required,
                    'search_key' => $this->joinText($name, $source['取引先カナ名'] ?? null, $source['取引先略称'] ?? null),
                    'legacy_code' => $sourceKey,
                    'legacy_name' => $originalName,
                    'note' => $this->customerMigrationNote($batch, $source),
                    'is_active' => true,
                    'disabled_at' => null,
                ],
            );

            $this->recordMapping($batch, $row, 'customers', $customer->id, $customer->wasRecentlyCreated ? 'created' : 'updated');
            $count++;
        }

        return ['count' => $count, 'name_fallbacks' => $nameFallbacks];
    }

    private function importProducts(AccessMigrationBatch $batch): int
    {
        $mainNames = $this->sourceRows($batch, '主商品')
            ->mapWithKeys(fn ($row) => [(string) $row->source_key => $this->payload($row)]);
        $units = Unit::query()->pluck('id', 'code');
        $taxCategories = ConsumptionTaxCategory::query()->pluck('id', 'code');
        $count = 0;

        foreach ($this->sourceRows($batch, '商品マスター') as $row) {
            $source = $this->payload($row);
            $sourceKey = (string) $row->source_key;
            $main = $mainNames->get((string) ($source['主商品ID'] ?? ''));
            try {
                $productNames = $this->productNameFormatter->format($source, $main);
            } catch (RuntimeException) {
                throw new RuntimeException("商品名を解決できません: {$sourceKey}");
            }

            $classification = trim((string) ($source['商品分類'] ?? ''));
            $productType = match ($classification) {
                '酒' => 'sake',
                '酒粕' => 'kasu',
                '食品' => 'food',
                default => 'goods',
            };
            $isAlcohol = $classification === '酒';
            $taxCode = match (true) {
                (bool) ($source['消費税非課税'] ?? false) => 'non_taxable',
                (bool) ($source['消費税軽減対象'] ?? false) => 'taxable_reduced',
                default => 'taxable_standard',
            };
            $baseUnitCode = $this->baseUnitCode($source['個数単位'] ?? null);
            $capacityUnitCode = $this->capacityUnitCode($source['容量単位'] ?? null, $isAlcohol);
            $productCode = 'ITARO-P-'.str_pad($sourceKey, 5, '0', STR_PAD_LEFT);
            $inventoryManaged = ! in_array($classification, ['送料', '値引', '保証金'], true);

            $product = Product::query()->updateOrCreate(
                ['product_code' => $productCode],
                [
                    'product_type' => $productType,
                    'name' => $productNames['name'],
                    'name_kana' => $this->nullIfBlank($main['カナ名称'] ?? null),
                    'display_name' => $productNames['display_name'],
                    'brand_name' => null,
                    'series_name' => null,
                    'style_name' => $this->nullIfBlank($source['商品サブネーム'] ?? null),
                    'category_name' => $classification !== '' ? $classification : 'その他',
                    'consumption_tax_category_id' => $taxCategories->get($taxCode)
                        ?? throw new RuntimeException("消費税区分がありません: {$taxCode}"),
                    'base_unit_id' => $units->get($baseUnitCode)
                        ?? throw new RuntimeException("単位がありません: {$baseUnitCode}"),
                    'sales_unit_id' => $units->get($baseUnitCode),
                    'inventory_unit_id' => $inventoryManaged ? $units->get($baseUnitCode) : null,
                    'capacity_value' => $this->numericOrNull($source['容量(ml)'] ?? null),
                    'capacity_unit_id' => $capacityUnitCode ? $units->get($capacityUnitCode) : null,
                    'alcohol_percentage' => $isAlcohol ? $this->numericOrNull($source['Alc%'] ?? null) : null,
                    'is_alcohol' => $isAlcohol,
                    'is_sales_available' => true,
                    'is_inventory_managed' => $inventoryManaged,
                    'search_key' => $productNames['search_key'],
                    'legacy_code' => $sourceKey,
                    'legacy_name' => $this->nullIfBlank($source['商品名'] ?? null),
                    'note' => "Access移行 batch={$batch->id}; 主商品ID=".($source['主商品ID'] ?? '').'; 商品分類='.$classification,
                    'is_active' => true,
                    'disabled_at' => null,
                ],
            );

            $this->syncProductDetail($product, $source, $productType);
            $this->recordMapping($batch, $row, 'products', $product->id, $product->wasRecentlyCreated ? 'created' : 'updated');
            $count++;
        }

        return $count;
    }

    private function syncProductDetail(Product $product, array $source, string $productType): void
    {
        if ($productType === 'sake') {
            $categoryCode = match ((string) ($source['酒類'] ?? '')) {
                '1' => 'seishu',
                '44' => 'liqueur',
                default => 'unresolved_liquor',
            };
            SakeProductDetail::query()->updateOrCreate(
                ['product_id' => $product->id],
                [
                    'liquor_tax_category_code' => $categoryCode,
                    'liquor_type_name' => match ($categoryCode) {
                        'seishu' => '清酒',
                        'liqueur' => 'リキュール',
                        default => '要確認',
                    },
                    'is_unpasteurized' => false,
                ],
            );

            return;
        }

        if ($productType === 'kasu') {
            KasuProductDetail::query()->updateOrCreate(
                ['product_id' => $product->id],
                ['kasu_type' => $product->name],
            );

            return;
        }

        if ($productType === 'food') {
            FoodProductDetail::query()->updateOrCreate(
                ['product_id' => $product->id],
                ['food_category' => $product->category_name],
            );

            return;
        }

        GoodsProductDetail::query()->updateOrCreate(
            ['product_id' => $product->id],
            ['goods_category' => $product->category_name],
        );
    }

    private function recordMapping(AccessMigrationBatch $batch, object $row, string $targetTable, int $targetId, string $action): void
    {
        DB::table('access_migration_mappings')->updateOrInsert(
            [
                'batch_id' => $batch->id,
                'source_table' => $row->source_table,
                'source_key' => $row->source_key,
            ],
            [
                'target_table' => $targetTable,
                'target_id' => (string) $targetId,
                'action' => $action,
                'source_payload_sha256' => $row->payload_sha256,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
        DB::table('access_migration_staging_rows')->where('id', $row->id)->update([
            'status' => 'imported',
            'target_table' => $targetTable,
            'target_id' => (string) $targetId,
            'updated_at' => now(),
        ]);
    }

    private function sourceRows(AccessMigrationBatch $batch, string $table)
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

    private function customerMigrationNote(AccessMigrationBatch $batch, array $source): string
    {
        return implode('; ', array_filter([
            "Access移行 batch={$batch->id}",
            '業種区分='.($source['業種区分'] ?? ''),
            '消費税未納取引='.(($source['消費税未納取引'] ?? false) ? 'true' : 'false'),
            $this->nullIfBlank($source['都道府県2'] ?? null) || $this->nullIfBlank($source['住所2'] ?? null)
                ? '第2住所='.($this->joinText($source['都道府県2'] ?? null, $source['住所2'] ?? null) ?? '')
                : null,
        ]));
    }

    private function baseUnitCode(mixed $unit): string
    {
        return match (trim((string) $unit)) {
            '本' => 'bottle',
            'ケース' => 'case',
            '箱' => 'box',
            default => 'piece',
        };
    }

    private function capacityUnitCode(mixed $unit, bool $isAlcohol): ?string
    {
        $unit = strtolower(trim((string) $unit));

        return match ($unit) {
            'ml' => 'milliliter',
            'l' => 'liter',
            'kg', 'kｇ' => 'kilogram',
            'g' => 'gram',
            default => $isAlcohol ? 'milliliter' : null,
        };
    }

    private function numericOrNull(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    private function nullIfBlank(mixed $value): ?string
    {
        $value = preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', (string) ($value ?? '')) ?? '';

        return $value === '' ? null : $value;
    }

    private function joinText(mixed ...$values): ?string
    {
        $values = array_values(array_filter(array_map($this->nullIfBlank(...), $values)));

        return $values === [] ? null : implode(' ', $values);
    }
}
