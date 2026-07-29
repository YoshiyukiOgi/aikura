<?php

namespace Tests\Feature;

use App\Models\AccessMigrationBatch;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SettlementReceivableCategory;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Services\ImportAccessPrices;
use App\Services\Pricing\ResolvePriceService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\PriceMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportAccessPricesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_access_default_and_customer_prices_idempotently(): void
    {
        $this->seed([
            CustomerMasterSeeder::class,
            ProductUnitMasterSeeder::class,
            PriceMasterSeeder::class,
        ]);

        [$batch, $customer, $product] = $this->prepareMappedCustomerAndProduct();

        $this->insertSourceRow($batch, '既定価格記録', '1', [
            '商品ID' => 11,
            '年月日' => '2025-01-01T00:00:00.0000000',
            '取引区分' => '卸価格',
            '単価' => 1800,
            'flg' => false,
        ]);
        $this->insertSourceRow($batch, '既定価格記録', '2', [
            '商品ID' => 11,
            '年月日' => '2025-03-25T00:00:00.0000000',
            '取引区分' => '卸価格',
            '単価' => 1925,
            'flg' => false,
        ]);
        $this->insertSourceRow($batch, '取引先別価格-商品', '3', [
            '取引先ID' => 1,
            '商品ID' => 11,
            '設定日' => '2006-05-01T00:00:00.0000000',
            '単価' => 1156,
            '既定単価' => 1191,
        ]);

        $summary = app(ImportAccessPrices::class)->import($batch);
        $summaryAgain = app(ImportAccessPrices::class)->import($batch->refresh());

        $this->assertSame(2, $summary['default_price_rules']);
        $this->assertSame(1, $summary['customer_price_rules']);
        $this->assertSame(2, $summaryAgain['default_price_rules']);
        $this->assertSame(1, $summaryAgain['customer_price_rules']);
        $this->assertDatabaseCount('price_rules', 3);

        $resolved = app(ResolvePriceService::class)->resolve($customer, $product, '2026-07-22', $product->sales_unit_id);

        $this->assertSame('1156.0000', $resolved->unitPrice);
        $this->assertSame('customer', $resolved->source);
        $this->assertDatabaseHas('price_rules', [
            'product_id' => $product->id,
            'transaction_category_id' => $customer->transaction_category_id,
            'unit_price' => '1925.0000',
            'effective_from' => '2025-03-25',
            'effective_to' => null,
        ]);
    }

    private function prepareMappedCustomerAndProduct(): array
    {
        $batch = AccessMigrationBatch::query()->create([
            'status' => 'completed',
            'source_file_name' => 'Itaro-xp.accdb',
            'source_file_path' => 'C:\\source\\Itaro-xp.accdb',
            'source_sha256' => str_repeat('C', 64),
            'source_size' => 123,
            'extractor_version' => 'test',
            'package_version' => 1,
            'source_table_count' => 3,
            'source_row_count' => 3,
            'manifest' => [],
            'started_at' => now(),
        ]);

        $transactionCategory = TransactionCategory::query()->where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::query()->where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::query()->where('code', 'monthly_end_next_month_end')->firstOrFail();
        $unit = Unit::query()->where('code', 'bottle')->firstOrFail();

        $customer = Customer::query()->create([
            'customer_code' => 'ITARO-C-0001',
            'name' => '安芸郡酒類卸商業協同組合',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
            'legacy_code' => '1',
        ]);
        $product = Product::query()->create([
            'product_code' => 'ITARO-P-00011',
            'product_type' => 'sake',
            'name' => '上撰玲瓏玉川 1800ml',
            'display_name' => '上撰玲瓏玉川 1800ml',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_alcohol' => true,
            'legacy_code' => '11',
        ]);

        DB::table('access_migration_mappings')->insert([
            [
                'batch_id' => $batch->id,
                'source_table' => '取引先マスター',
                'source_key' => '1',
                'target_table' => 'customers',
                'target_id' => (string) $customer->id,
                'action' => 'imported',
                'source_payload_sha256' => str_repeat('A', 64),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'batch_id' => $batch->id,
                'source_table' => '商品マスター',
                'source_key' => '11',
                'target_table' => 'products',
                'target_id' => (string) $product->id,
                'action' => 'imported',
                'source_payload_sha256' => str_repeat('B', 64),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        return [$batch, $customer, $product];
    }

    private function insertSourceRow(AccessMigrationBatch $batch, string $table, string $key, array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        DB::table('access_migration_staging_rows')->insert([
            'batch_id' => $batch->id,
            'source_table' => $table,
            'source_row_number' => DB::table('access_migration_staging_rows')->where('batch_id', $batch->id)->count() + 1,
            'source_key' => $key,
            'payload' => $json,
            'payload_sha256' => strtoupper(hash('sha256', $json)),
            'status' => 'staged',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
