<?php

namespace Tests\Feature;

use App\Models\AccessMigrationBatch;
use App\Models\Customer;
use App\Models\Product;
use App\Services\ImportAccessMasters;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\LiquorTaxMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\TaxMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportAccessMastersTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_customers_and_products_idempotently_with_source_mappings(): void
    {
        $this->seed(CustomerMasterSeeder::class);
        $this->seed(ProductUnitMasterSeeder::class);
        $this->seed(TaxMasterSeeder::class);
        $this->seed(LiquorTaxMasterSeeder::class);

        $batch = AccessMigrationBatch::query()->create([
            'status' => 'ready_with_warnings',
            'source_file_name' => 'Itaro-xp.accdb',
            'source_file_path' => 'C:\\source\\Itaro-xp.accdb',
            'source_sha256' => str_repeat('A', 64),
            'source_size' => 123,
            'extractor_version' => 'test',
            'package_version' => 1,
            'source_table_count' => 3,
            'source_row_count' => 3,
            'manifest' => [],
            'started_at' => now(),
        ]);

        $this->insertSourceRow($batch, '取引先マスター', '25', [
            '取引先ID' => 25,
            '取引先名' => '移行得意先',
            '取引先カナ名' => 'イコウトクイサキ',
            '取引区分' => 'その他',
            '業種区分' => '自家用',
            '閉め日' => 25,
            '消費税未納取引' => true,
        ]);
        $this->insertSourceRow($batch, '主商品', '11', [
            '主商品ID' => 11,
            '主商品名' => '主商品名',
            'カナ名称' => 'シュショウヒン',
        ]);
        $this->insertSourceRow($batch, '商品マスター', '13', [
            '商品ID' => 13,
            '主商品ID' => 11,
            '商品名' => '',
            '商品サブネーム' => '原酒',
            '商品分類' => '酒',
            '酒類' => 1,
            'Alc%' => 19.4,
            '容量(ml)' => 720,
            '容量単位' => 'ml',
            '個数単位' => '本',
            '消費税非課税' => false,
            '消費税軽減対象' => false,
        ]);

        $summary = app(ImportAccessMasters::class)->import($batch);
        $summaryAgain = app(ImportAccessMasters::class)->import($batch->refresh());

        $this->assertSame(1, $summary['customers']);
        $this->assertSame(0, $summary['customer_name_fallbacks']);
        $this->assertSame(1, $summary['products']);
        $this->assertSame(2, $summary['mappings']);
        $this->assertSame(2, $summaryAgain['mappings']);
        $this->assertSame('masters_imported', $batch->refresh()->status);

        $customer = Customer::query()->where('customer_code', 'ITARO-C-0025')->firstOrFail();
        $this->assertSame('移行得意先', $customer->name);
        $this->assertSame('ceil', $customer->tax_rounding_method);
        $this->assertSame('invoice', $customer->tax_calculation_unit);
        $this->assertSame(25, $customer->billingCycle->closing_day);
        $this->assertSame('internal_balance', $customer->settlementReceivableCategory->code);

        $product = Product::query()->where('product_code', 'ITARO-P-00013')->firstOrFail();
        $this->assertSame('主商品名', $product->name);
        $this->assertSame('主商品名 原酒 720ml', $product->display_name);
        $this->assertSame('19.40', $product->alcohol_percentage);
        $this->assertSame('seishu', $product->sakeDetail->liquor_tax_category_code);
        $this->assertSame('taxable_standard', $product->consumptionTaxCategory->code);
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_it_uses_the_short_name_when_the_access_customer_name_is_full_width_spaces(): void
    {
        $this->seed(CustomerMasterSeeder::class);
        $this->seed(ProductUnitMasterSeeder::class);
        $this->seed(TaxMasterSeeder::class);

        $batch = AccessMigrationBatch::query()->create([
            'status' => 'ready',
            'source_file_name' => 'Itaro-xp.accdb',
            'source_file_path' => 'C:\\source\\Itaro-xp.accdb',
            'source_sha256' => str_repeat('B', 64),
            'source_size' => 123,
            'extractor_version' => 'test',
            'package_version' => 1,
            'source_table_count' => 3,
            'source_row_count' => 3,
            'manifest' => [],
            'started_at' => now(),
        ]);
        $this->insertSourceRow($batch, '取引先マスター', '5948', [
            '取引先ID' => 5948,
            '取引先名' => '　　　　　　　　　',
            '取引先略称' => '従/　　　　　　　 ',
            '取引先カナ名' => '従/その他',
            '取引区分' => '生産者価格',
            '業種区分' => '従業員',
        ]);
        $this->insertSourceRow($batch, '主商品', '11', ['主商品ID' => 11, '主商品名' => '商品']);
        $this->insertSourceRow($batch, '商品マスター', '11', [
            '商品ID' => 11,
            '主商品ID' => 11,
            '商品分類' => 'その他',
            '個数単位' => '個',
        ]);

        $summary = app(ImportAccessMasters::class)->import($batch);

        $this->assertSame(1, $summary['customer_name_fallbacks']);
        $this->assertSame('従/', Customer::query()->where('customer_code', 'ITARO-C-5948')->value('name'));
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
