<?php

namespace Tests\Feature;

use App\Models\AccessMigrationBatch;
use App\Models\ShipmentHeader;
use App\Services\ImportAccessMasters;
use App\Services\ImportAccessShipments;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\LiquorTaxMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\TaxMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportAccessShipmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_confirmed_history_idempotently_without_inventory_movements(): void
    {
        $this->seed(CustomerMasterSeeder::class);
        $this->seed(ProductUnitMasterSeeder::class);
        $this->seed(TaxMasterSeeder::class);
        $this->seed(LiquorTaxMasterSeeder::class);

        $batch = AccessMigrationBatch::query()->create([
            'status' => 'ready',
            'source_file_name' => 'Itaro-xp.accdb',
            'source_file_path' => 'C:\\source\\Itaro-xp.accdb',
            'source_sha256' => str_repeat('C', 64),
            'source_size' => 123,
            'extractor_version' => 'test',
            'package_version' => 1,
            'source_table_count' => 5,
            'source_row_count' => 5,
            'manifest' => [],
            'started_at' => now(),
        ]);
        $this->insertSourceRow($batch, '取引先マスター', '25', [
            '取引先ID' => 25, '取引先名' => '輸出先', '取引区分' => 'その他', '業種区分' => '輸出',
        ]);
        $this->insertSourceRow($batch, '主商品', '11', ['主商品ID' => 11, '主商品名' => '清酒']);
        $this->insertSourceRow($batch, '商品マスター', '13', [
            '商品ID' => 13, '主商品ID' => 11, '商品分類' => '酒', '酒類' => 1,
            'Alc%' => 15, '容量(ml)' => 720, '容量単位' => 'ml', '個数単位' => '本',
        ]);
        $this->insertSourceRow($batch, '出荷伝票・取引先', '100', [
            '伝票番号' => 100, '年月日' => '2025-06-30', '取引先ID' => 25,
            '金額' => 1000, '消費税額' => 0, '合計' => 1000, '酒税区分' => 6,
            '戻入取引' => false, '酒税未納取引' => false, '輸出取引' => true,
        ]);
        $this->insertSourceRow($batch, '出荷伝票・商品', '200', [
            'ID' => 200, '伝票番号' => 100, '商品ID' => 13, '商品詳細ID' => 77,
            '個数' => 1, '単価' => 1000, '取引額' => 1000, '商品税額' => 0,
            '消費税率' => 0, '酒税' => 100000, '軽減率' => 80,
        ]);

        app(ImportAccessMasters::class)->import($batch);
        $summary = app(ImportAccessShipments::class)->import($batch->refresh());
        $summaryAgain = app(ImportAccessShipments::class)->import($batch->refresh());

        $this->assertSame(1, $summary['shipment_headers']);
        $this->assertSame(1, $summary['shipment_lines']);
        $this->assertSame(0, $summary['stock_movements_created']);
        $this->assertSame(1, $summaryAgain['shipment_headers']);
        $this->assertDatabaseCount('shipment_headers', 1);
        $this->assertDatabaseCount('shipment_lines', 1);
        $this->assertDatabaseCount('stock_movements', 0);

        $shipment = ShipmentHeader::query()->with('lines')->sole();
        $line = $shipment->lines->sole();
        $this->assertSame('confirmed', $shipment->status);
        $this->assertSame('export_exempt', $shipment->confirmed_liquor_tax_treatment);
        $this->assertSame('1000.00', $shipment->legacy_access_total_amount);
        $this->assertSame('0.2000', $line->confirmed_liquor_tax_reduction_rate);
        $this->assertSame('0.000720', $line->confirmed_liquor_taxable_kl);
        $this->assertSame('0.00', $line->confirmed_liquor_tax_estimated_amount);
        $this->assertSame('export_exempt', $line->confirmed_consumption_taxability);
        $this->assertSame('77', $line->legacy_access_detail_id);
        $this->assertDatabaseHas('access_migration_mappings', [
            'batch_id' => $batch->id, 'source_table' => '出荷伝票・商品',
            'source_key' => '200', 'target_table' => 'shipment_lines',
        ]);
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
