<?php

namespace Tests\Feature;

use App\Models\AccessMigrationBatch;
use App\Models\ShipmentHeader;
use App\Services\ApplyAccessMigrationDelta;
use App\Services\ImportAccessMasters;
use App\Services\ImportAccessShipments;
use App\Services\PlanAccessMigrationDelta;
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
            '請求年' => 2025, '請求月' => 7,
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
        $this->assertSame(2025, $shipment->legacy_access_billing_year);
        $this->assertSame(7, $shipment->legacy_access_billing_month);
        $this->assertSame('0.2000', $line->confirmed_liquor_tax_reduction_rate);
        $this->assertSame('0.000720', $line->confirmed_liquor_taxable_kl);
        $this->assertSame('0.00', $line->confirmed_liquor_tax_estimated_amount);
        $this->assertSame('export_exempt', $line->confirmed_consumption_taxability);
        $this->assertSame('77', $line->legacy_access_detail_id);
        $this->assertDatabaseHas('access_migration_mappings', [
            'batch_id' => $batch->id, 'source_table' => '出荷伝票・商品',
            'source_key' => '200', 'target_table' => 'shipment_lines',
        ]);

        // A reviewed selection must not pull unrelated new rows or omit-table rows.
        $this->insertSourceRow($batch, '出荷伝票・取引先', '101', [
            '伝票番号' => 101, '年月日' => '2025-07-01', '取引先ID' => 25,
        ]);
        $this->insertSourceRow($batch, '出荷伝票・商品', '201', [
            'ID' => 201, '伝票番号' => 100, '商品ID' => 13, '個数' => 99,
        ]);
        $selected = app(ImportAccessShipments::class)->import(
            $batch->refresh(), true, null, ['出荷伝票・取引先' => ['100']],
        );
        $this->assertSame(1, $selected['shipment_headers']);
        $this->assertSame(0, $selected['shipment_lines']);
        $this->assertDatabaseCount('shipment_headers', 1);
        $this->assertDatabaseCount('shipment_lines', 1);
        $this->assertDatabaseMissing('access_migration_mappings', [
            'batch_id' => $batch->id, 'source_table' => '出荷伝票・商品', 'source_key' => '201',
        ]);
    }

    public function test_delta_import_updates_a_changed_shipment_header_when_the_month_is_open(): void
    {
        $this->seed(CustomerMasterSeeder::class);
        $this->seed(ProductUnitMasterSeeder::class);
        $this->seed(TaxMasterSeeder::class);
        $this->seed(LiquorTaxMasterSeeder::class);

        $baseline = $this->createShipmentBatch('D');
        $current = $this->createShipmentBatch('E');
        foreach ([$baseline, $current] as $batch) {
            $this->insertSourceRow($batch, '取引先マスター', '25', ['取引先ID' => 25, '取引先名' => '差分取引先', '取引区分' => '卸価格']);
            $this->insertSourceRow($batch, '主商品', '11', ['主商品ID' => 11, '主商品名' => '差分商品']);
            $this->insertSourceRow($batch, '商品マスター', '13', ['商品ID' => 13, '主商品ID' => 11, '商品分類' => '酒', '酒類' => 1, '容量(ml)' => 720, '容量単位' => 'ml', '個数単位' => '本']);
            $this->insertSourceRow($batch, '出荷伝票・商品', '200', ['ID' => 200, '伝票番号' => 100, '商品ID' => 13, '商品詳細ID' => 77, '個数' => 1, '単価' => 1000, '取引額' => 1000, '商品税額' => 100, '消費税率' => 10]);
        }
        $this->insertSourceRow($baseline, '出荷伝票・取引先', '100', ['伝票番号' => 100, '年月日' => '2026-08-01', '請求年' => 2026, '請求月' => 8, '取引先ID' => 25, '金額' => 1000, '消費税額' => 100, '合計' => 1100, '酒税区分' => 1]);
        $this->insertSourceRow($current, '出荷伝票・取引先', '100', ['伝票番号' => 100, '年月日' => '2026-08-01', '請求年' => 2026, '請求月' => 9, '取引先ID' => 25, '金額' => 1250, '消費税額' => 125, '合計' => 1375, '酒税区分' => 1]);
        $changedLine = ['ID' => 200, '伝票番号' => 100, '商品ID' => 13, '商品詳細ID' => 77, '個数' => 1, '単価' => 1000, '取引額' => 1000, '商品税額' => 100, '消費税率' => 10, '摘要' => '変更後の配送指定'];
        $changedLineJson = json_encode($changedLine, JSON_UNESCAPED_UNICODE);
        DB::table('access_migration_staging_rows')
            ->where('batch_id', $current->id)->where('source_table', '出荷伝票・商品')->where('source_key', '200')
            ->update(['payload' => $changedLineJson, 'payload_sha256' => strtoupper(hash('sha256', $changedLineJson))]);

        app(ImportAccessMasters::class)->import($baseline);
        app(ImportAccessShipments::class)->import($baseline->refresh());
        $baseline->update(['status' => 'completed']);
        app(PlanAccessMigrationDelta::class)->plan($current, $baseline);
        app(ImportAccessMasters::class)->import($current->fresh());
        $current->update(['status' => 'prices_imported']);

        $summary = app(ImportAccessShipments::class)->import($current->fresh(), true);

        $this->assertSame(1, $summary['shipment_headers']);
        $this->assertSame(1, $summary['shipment_lines']);
        $shipment = ShipmentHeader::query()->where('legacy_access_document_number', '100')->sole();
        $this->assertSame('1375.00', $shipment->legacy_access_total_amount);
        $this->assertSame(2026, $shipment->legacy_access_billing_year);
        $this->assertSame(9, $shipment->legacy_access_billing_month);
        $this->assertSame(1, $shipment->lines()->sole()->line_no);
    }

    public function test_delta_apply_stops_a_changed_shipment_that_is_already_on_a_confirmed_invoice(): void
    {
        $this->seed(CustomerMasterSeeder::class);
        $this->seed(ProductUnitMasterSeeder::class);
        $this->seed(TaxMasterSeeder::class);
        $this->seed(LiquorTaxMasterSeeder::class);

        $baseline = $this->createShipmentBatch('F');
        $current = $this->createShipmentBatch('G');
        foreach ([$baseline, $current] as $batch) {
            $this->insertSourceRow($batch, '取引先マスター', '25', ['取引先ID' => 25, '取引先名' => '請求済差分取引先', '取引区分' => '卸価格']);
            $this->insertSourceRow($batch, '主商品', '11', ['主商品ID' => 11, '主商品名' => '請求済差分商品']);
            $this->insertSourceRow($batch, '商品マスター', '13', ['商品ID' => 13, '主商品ID' => 11, '商品分類' => '酒', '酒類' => 1, '容量(ml)' => 720, '容量単位' => 'ml', '個数単位' => '本']);
            $this->insertSourceRow($batch, '出荷伝票・商品', '200', ['ID' => 200, '伝票番号' => 100, '商品ID' => 13, '商品詳細ID' => 77, '個数' => 1, '単価' => 1000, '取引額' => 1000, '商品税額' => 100, '消費税率' => 10]);
        }
        $this->insertSourceRow($baseline, '出荷伝票・取引先', '100', ['伝票番号' => 100, '年月日' => '2026-08-01', '取引先ID' => 25, '金額' => 1000, '消費税額' => 100, '合計' => 1100, '酒税区分' => 1]);
        $this->insertSourceRow($current, '出荷伝票・取引先', '100', ['伝票番号' => 100, '年月日' => '2026-08-01', '取引先ID' => 25, '金額' => 1250, '消費税額' => 125, '合計' => 1375, '酒税区分' => 1]);

        app(ImportAccessMasters::class)->import($baseline);
        app(ImportAccessShipments::class)->import($baseline->refresh());
        $shipment = ShipmentHeader::query()->with('lines')->sole();
        $line = $shipment->lines->sole();
        $invoiceId = DB::table('invoice_headers')->insertGetId([
            'invoice_number' => 'TEST-202608-001', 'status' => 'confirmed',
            'customer_id' => $shipment->customer_id, 'billing_cycle_id' => $shipment->billing_cycle_id,
            'invoice_date' => '2026-08-31', 'billing_period_start' => '2026-08-01', 'billing_period_end' => '2026-08-31',
            'subtotal_amount' => 1000, 'tax_amount' => 100, 'total_amount' => 1100,
            'confirmed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('invoice_lines')->insert([
            'invoice_header_id' => $invoiceId, 'shipment_header_id' => $shipment->id, 'shipment_line_id' => $line->id,
            'line_no' => 1, 'product_id' => $line->product_id,
            'product_code' => $line->confirmed_product_code, 'product_name' => $line->confirmed_product_name,
            'display_name' => $line->confirmed_display_name, 'quantity' => 1,
            'unit_code' => $line->confirmed_unit_code, 'unit_name' => $line->confirmed_unit_name,
            'unit_price' => 1000, 'amount' => 1000, 'tax_rate' => 0.1, 'tax_amount' => 100, 'total_amount' => 1100,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $baseline->update(['status' => 'completed']);
        app(PlanAccessMigrationDelta::class)->plan($current, $baseline);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('請求確定済み伝票の変更');
        $this->expectExceptionMessage('出荷100/請求TEST-202608-001');

        app(ApplyAccessMigrationDelta::class)->apply($current->refresh());
    }

    private function createShipmentBatch(string $hashCharacter): AccessMigrationBatch
    {
        return AccessMigrationBatch::query()->create([
            'status' => 'ready', 'source_file_name' => 'Itaro-xp.accdb', 'source_file_path' => 'C:\\source\\Itaro-xp.accdb',
            'source_sha256' => str_repeat($hashCharacter, 64), 'source_size' => 123, 'extractor_version' => 'test', 'package_version' => 1,
            'source_table_count' => 5, 'source_row_count' => 5, 'manifest' => [], 'started_at' => now(),
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
