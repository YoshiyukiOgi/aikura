<?php

namespace Tests\Feature;

use App\Exceptions\Inventory\InventoryReportExportException;
use App\Models\AppSetting;
use App\Models\ProductionLot;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\Inventory\GenerateLotStockBalanceReportService;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\StockLocationSeeder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateInventoryReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(Filesystem::class)->deleteDirectory(storage_path('app/reports/inventory'));

        parent::tearDown();
    }

    public function test_it_generates_lot_stock_balance_report_file_and_export_record(): void
    {
        [$unit, $location] = $this->prepareMasterData();
        $lot = $this->createLot($unit, $location);
        $movement = $this->createMovement($unit, $location, $lot, 'confirmed', '10.0000');
        $this->createMovement($unit, $location, $lot, 'confirmed', '-3.0000');

        $export = app(GenerateLotStockBalanceReportService::class)
            ->generate(reason: 'lot stock balance report');

        $absolutePath = storage_path('app/'.$export->file_path);
        $content = file_get_contents($absolutePath);

        $this->assertSame('lot_stock_balance', $export->report_type);
        $this->assertSame('txt', $export->format);
        $this->assertSame('generated', $export->status);
        $this->assertSame($movement->id, $export->exportable_id);
        $this->assertSame('text/plain', $export->mime_type);
        $this->assertFileExists($absolutePath);
        $this->assertStringContainsString('Lot Stock Balance Report', $content);
        $this->assertStringContainsString('Total Physical Quantity: 7.0000', $content);
        $this->assertStringContainsString('Total Available Quantity: 7.0000', $content);
        $this->assertStringContainsString((string) $lot->id, $content);
        $this->assertSame(strlen($content), $export->file_size);
        $this->assertSame(hash('sha256', $content), $export->checksum_sha256);

        $this->assertDatabaseHas('report_exports', [
            'id' => $export->id,
            'report_type' => 'lot_stock_balance',
            'format' => 'txt',
            'exportable_type' => StockMovement::class,
            'exportable_id' => $movement->id,
            'reason' => 'lot stock balance report',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'lot_stock_balance_report.generated',
            'target_table' => 'stock_movements',
            'target_id' => 'current-lot',
            'reason' => 'lot stock balance report',
        ]);
    }

    public function test_it_keeps_reissued_lot_stock_balance_reports_as_separate_files(): void
    {
        [$unit, $location] = $this->prepareMasterData();
        $lot = $this->createLot($unit, $location);
        $this->createMovement($unit, $location, $lot, 'confirmed', '10.0000');

        $first = app(GenerateLotStockBalanceReportService::class)
            ->generate(reason: 'first lot stock balance report');
        $second = app(GenerateLotStockBalanceReportService::class)
            ->generate(reason: 'second lot stock balance report');

        $this->assertNotSame($first->id, $second->id);
        $this->assertNotSame($first->file_path, $second->file_path);
        $this->assertFileExists(storage_path('app/'.$first->file_path));
        $this->assertFileExists(storage_path('app/'.$second->file_path));
    }

    public function test_it_rejects_empty_lot_stock_balance_report_generation(): void
    {
        $this->expectException(InventoryReportExportException::class);

        app(GenerateLotStockBalanceReportService::class)->generate();
    }

    public function test_it_rejects_unsupported_lot_stock_balance_report_format(): void
    {
        [$unit, $location] = $this->prepareMasterData();
        $lot = $this->createLot($unit, $location);
        $this->createMovement($unit, $location, $lot, 'confirmed', '10.0000');

        $this->expectException(InventoryReportExportException::class);

        app(GenerateLotStockBalanceReportService::class)->generate('pdf');
    }

    /**
     * @return array{0: Unit, 1: StockLocation}
     */
    private function prepareMasterData(): array
    {
        $this->seed([
            ProductUnitMasterSeeder::class,
            StockLocationSeeder::class,
        ]);

        $unit = Unit::where('code', 'bottle')->firstOrFail();
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();
        AppSetting::setValue('operational_start_date', '2026-06-01');

        return [$unit, $location];
    }

    private function createLot(Unit $unit, StockLocation $location): ProductionLot
    {
        return ProductionLot::create([
            'lot_code' => 'INVENTORY-REPORT-LOT-001',
            'display_name' => 'Inventory Report Lot 001',
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'status' => 'active',
            'is_active' => true,
            'production_date' => '2026-06-01',
        ]);
    }

    private function createMovement(
        Unit $unit,
        StockLocation $location,
        ProductionLot $lot,
        string $status,
        string $quantity,
    ): StockMovement {
        return StockMovement::create([
            'status' => $status,
            'movement_type' => 'inventory_adjustment',
            'movement_date' => '2026-06-15',
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'quantity' => $quantity,
            'production_lot_id' => $lot->id,
            'lot_code' => $lot->lot_code,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
            'closed_at' => $status === 'closed' ? now() : null,
        ]);
    }
}
