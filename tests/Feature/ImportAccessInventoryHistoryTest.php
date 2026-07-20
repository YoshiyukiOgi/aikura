<?php

namespace Tests\Feature;

use App\Models\AccessMigrationBatch;
use App\Models\NonSalesStockOperationHeader;
use App\Services\ImportAccessInventoryHistory;
use App\Services\ImportAccessMasters;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\LiquorTaxMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\StockLocationSeeder;
use Database\Seeders\TaxMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportAccessInventoryHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_historical_inventory_operations_idempotently_without_current_stock_movements(): void
    {
        $this->seed(CustomerMasterSeeder::class);
        $this->seed(ProductUnitMasterSeeder::class);
        $this->seed(TaxMasterSeeder::class);
        $this->seed(LiquorTaxMasterSeeder::class);
        $this->seed(StockLocationSeeder::class);

        $batch = AccessMigrationBatch::query()->create([
            'status' => 'ready', 'source_file_name' => 'Itaro-xp.accdb',
            'source_file_path' => 'C:\\source\\Itaro-xp.accdb', 'source_sha256' => str_repeat('D', 64),
            'source_size' => 123, 'extractor_version' => 'test', 'package_version' => 1,
            'source_table_count' => 6, 'source_row_count' => 6, 'manifest' => [], 'started_at' => now(),
        ]);
        $this->insertSourceRow($batch, '取引先マスター', '1', ['取引先ID' => 1, '取引先名' => '得意先']);
        $this->insertSourceRow($batch, '主商品', '11', ['主商品ID' => 11, '主商品名' => '清酒']);
        $this->insertSourceRow($batch, '商品マスター', '13', [
            '商品ID' => 13, '主商品ID' => 11, '商品分類' => '酒', '酒類' => 1,
            'Alc%' => 15, '容量(ml)' => 720, '容量単位' => 'ml', '個数単位' => '本',
        ]);
        $this->insertSourceRow($batch, '商品詳細名称', '71', ['ID' => 71, '商品ID' => 13, '商品詳細名称' => '詰替前']);
        $this->insertSourceRow($batch, '商品詳細名称', '72', ['ID' => 72, '商品ID' => 13, '商品詳細名称' => '詰替後']);
        $this->insertSourceRow($batch, '伝票外在庫出入', '100', [
            'ID' => 100, '年月日' => '2025-06-01', '伝票外在庫出入ID' => 2, '増減数' => 0,
            '増商品ID' => 13, '増商品詳細ID' => 72, '増個数' => 2, '増商品酒税' => 100000, '増商品軽減率' => 80,
            '減商品ID' => 13, '減商品詳細ID' => 71, '減個数' => 2, '減商品酒税' => 100000, '減商品軽減率' => 80,
        ]);
        $this->insertSourceRow($batch, '伝票外在庫出入', '101', [
            'ID' => 101, '年月日' => '2025-06-02', '伝票外在庫出入ID' => 3, '増減数' => -720,
            '減商品ID' => 13, '減商品詳細ID' => 72, '減個数' => 1, '減商品酒税' => 100000, '減商品軽減率' => 80,
        ]);

        app(ImportAccessMasters::class)->import($batch);
        $batch->refresh()->update(['status' => 'shipments_imported']);
        $summary = app(ImportAccessInventoryHistory::class)->import($batch->refresh());
        $summaryAgain = app(ImportAccessInventoryHistory::class)->import($batch->refresh());

        $this->assertSame(2, $summary['production_lots']);
        $this->assertSame(2, $summary['operation_headers']);
        $this->assertSame(3, $summary['operation_lines']);
        $this->assertSame(1, $summary['tax_review_headers']);
        $this->assertSame(0, $summary['stock_movements_created']);
        $this->assertSame(2, $summaryAgain['operation_headers']);
        $this->assertDatabaseCount('production_lots', 2);
        $this->assertDatabaseCount('non_sales_stock_operation_headers', 2);
        $this->assertDatabaseCount('non_sales_stock_operation_lines', 3);
        $this->assertDatabaseCount('stock_movements', 0);

        $repackaging = NonSalesStockOperationHeader::query()->with('lines')->where('legacy_access_stock_operation_id', '100')->sole();
        $breakage = NonSalesStockOperationHeader::query()->with('lines')->where('legacy_access_stock_operation_id', '101')->sole();
        $this->assertSame('repackaging', $repackaging->operation_type);
        $this->assertSame(['2.0000', '-2.0000'], $repackaging->lines->pluck('quantity')->all());
        $this->assertSame('review', $breakage->liquor_tax_treatment);
        $this->assertTrue($breakage->requires_tax_review);
        $this->assertSame('-1.0000', $breakage->lines->sole()->quantity);
        $this->assertSame('0.2000', $breakage->lines->sole()->liquor_tax_reduction_rate);
        $this->assertSame('57.60', $breakage->lines->sole()->liquor_tax_estimated_amount);
    }

    private function insertSourceRow(AccessMigrationBatch $batch, string $table, string $key, array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        DB::table('access_migration_staging_rows')->insert([
            'batch_id' => $batch->id, 'source_table' => $table,
            'source_row_number' => DB::table('access_migration_staging_rows')->where('batch_id', $batch->id)->count() + 1,
            'source_key' => $key, 'payload' => $json,
            'payload_sha256' => strtoupper(hash('sha256', $json)), 'status' => 'staged',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
