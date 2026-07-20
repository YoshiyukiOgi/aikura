<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\Inventory\ConfirmInventoryCountService;
use App\Services\Inventory\ConfirmStockMonthlyBalanceService;
use App\Services\Inventory\CancelNonSalesStockOperationService;
use App\Services\Inventory\CreateInventoryCountDraftService;
use App\Services\Inventory\CreateNonSalesStockOperationData;
use App\Services\Inventory\CreateNonSalesStockOperationLineData;
use App\Services\Inventory\CreateNonSalesStockOperationService;
use App\Services\Inventory\CreateStockMonthlyBalanceDraftService;
use App\Services\Inventory\CurrentStockBalanceService;
use App\Services\Inventory\LotStockBalanceService;
use App\Services\Inventory\ReverseStockMovementService;
use App\Services\Inventory\SaveInventoryCountService;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Database\Seeders\TaxMasterSeeder;
use App\Services\Tax\AggregateMonthlyLiquorTaxTransfersService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryOperationsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_count_creates_lot_adjustment_on_target_month_end(): void
    {
        [$product, $unit, $location, $lot] = $this->masters();
        $this->movement($product, $unit, $location, $lot, '2026-06-10', '10.0000');

        $count = app(CreateInventoryCountDraftService::class)->create(2026, 6);
        $line = $count->lines->firstOrFail();
        $this->assertSame('10.0000', $line->book_quantity);

        $count = app(SaveInventoryCountService::class)->save($count, [[
            'id' => $line->id, 'counted_quantity' => '8.0000', 'reason' => '破損差異',
        ]]);
        $count = app(ConfirmInventoryCountService::class)->confirm($count, '2026年6月棚卸確定');

        $this->assertSame('confirmed', $count->status);
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'inventory_adjustment', 'movement_date' => '2026-06-30',
            'production_lot_id' => $lot->id, 'quantity' => '-2.0000', 'source_document_number' => 'COUNT-202606',
        ]);
        $this->assertSame('8.0000', app(CurrentStockBalanceService::class)->forProductLocationUnit($product->id, $location->id, $unit->id)->physicalQuantity);
    }

    public function test_current_stock_uses_confirmed_monthly_product_and_lot_balances_plus_later_movements(): void
    {
        [$product, $unit, $location, $lot] = $this->masters();
        $this->movement($product, $unit, $location, $lot, '2026-05-10', '10.0000');
        app(CreateStockMonthlyBalanceDraftService::class)->create(2026, 5, 'May close');
        app(ConfirmStockMonthlyBalanceService::class)->confirm(2026, 5, 'May close');
        $this->movement($product, $unit, $location, $lot, '2026-06-02', '-3.0000', 'shipment');

        $stock = app(CurrentStockBalanceService::class)->forProductLocationUnit($product->id, $location->id, $unit->id);
        $lotStock = app(LotStockBalanceService::class)->forLotProductLocationUnit($lot->id, $product->id, $location->id, $unit->id);
        $this->assertSame('7.0000', $stock->physicalQuantity);
        $this->assertSame('7.0000', $lotStock->physicalQuantity);
    }

    public function test_self_consumption_keeps_tax_treatments_and_reduces_stock(): void
    {
        [$product, $unit, $location, $lot] = $this->masters();
        $this->movement($product, $unit, $location, $lot, '2026-07-01', '5.0000');
        $operation = app(CreateNonSalesStockOperationService::class)->create(new CreateNonSalesStockOperationData(
            operationType: 'self_consumption', operationDate: '2026-07-15', reason: '社内試飲',
            lines: [new CreateNonSalesStockOperationLineData(productId: $product->id, stockLocationId: $location->id, unitId: $unit->id, quantity: '-1.0000', productionLotId: $lot->id, lotCode: $lot->lot_code)],
            consumptionTaxTreatment: 'non_taxable', liquorTaxTreatment: 'taxable_transfer',
        ));

        $this->assertSame('non_taxable', $operation->consumption_tax_treatment);
        $this->assertSame('taxable_transfer', $operation->liquor_tax_treatment);
        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'non_sales_self_consumption', 'quantity' => '-1.0000']);
        $summary = app(AggregateMonthlyLiquorTaxTransfersService::class)->aggregate(2026, 7)->firstOrFail();
        $this->assertSame('0.000720', $summary->taxableKl);
        $this->assertSame('72.00', $summary->estimatedAmount);
    }

    public function test_stock_movement_correction_posts_an_opposite_movement(): void
    {
        [$product, $unit, $location, $lot] = $this->masters();
        $original = $this->movement($product, $unit, $location, $lot, '2026-07-01', '5.0000');

        $correction = app(ReverseStockMovementService::class)->reverse($original, '2026-07-15', 'Incorrect receipt');

        $this->assertSame('stock_correction', $correction->movement_type);
        $this->assertSame('-5.0000', $correction->quantity);
        $this->assertSame($original->id, $correction->related_stock_movement_id);
        $this->assertSame('0.0000', app(CurrentStockBalanceService::class)->forProductLocationUnit($product->id, $location->id, $unit->id)->physicalQuantity);
    }

    public function test_cancelling_self_consumption_restores_stock_and_excludes_liquor_tax(): void
    {
        [$product, $unit, $location, $lot] = $this->masters();
        $this->movement($product, $unit, $location, $lot, '2026-07-01', '5.0000');
        $operation = app(CreateNonSalesStockOperationService::class)->create(new CreateNonSalesStockOperationData(
            operationType: 'self_consumption', operationDate: '2026-07-15', reason: 'Internal tasting',
            lines: [new CreateNonSalesStockOperationLineData(productId: $product->id, stockLocationId: $location->id, unitId: $unit->id, quantity: '-1.0000', productionLotId: $lot->id, lotCode: $lot->lot_code)],
            consumptionTaxTreatment: 'non_taxable', liquorTaxTreatment: 'taxable_transfer',
        ));

        $cancelled = app(CancelNonSalesStockOperationService::class)->cancel($operation, '2026-07-16', 'Entered in error');

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'non_sales_cancellation', 'quantity' => '1.0000',
            'source_document_number' => $operation->operation_number,
        ]);
        $this->assertSame('5.0000', app(CurrentStockBalanceService::class)->forProductLocationUnit($product->id, $location->id, $unit->id)->physicalQuantity);
        $this->assertTrue(app(AggregateMonthlyLiquorTaxTransfersService::class)->aggregate(2026, 7)->isEmpty());
    }

    private function masters(): array
    {
        $this->seed([ProductUnitMasterSeeder::class, TaxMasterSeeder::class, ShipmentMasterSeeder::class]);
        $unit = Unit::where('code', 'bottle')->firstOrFail();
        $capacityUnit = Unit::where('code', 'milliliter')->firstOrFail();
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();
        $product = Product::create(['product_code' => 'INV-WORK-001', 'product_type' => 'sake', 'name' => 'Inventory Workflow Sake', 'display_name' => 'Inventory Workflow Sake', 'base_unit_id' => $unit->id, 'sales_unit_id' => $unit->id, 'inventory_unit_id' => $unit->id, 'capacity_value' => '720.0000', 'capacity_unit_id' => $capacityUnit->id, 'alcohol_percentage' => '15.00', 'is_alcohol' => true, 'is_inventory_managed' => true]);
        $lot = ProductionLot::create(['lot_code' => 'INV-WORK-LOT-001', 'display_name' => 'Inventory Workflow Lot', 'product_id' => $product->id, 'stock_location_id' => $location->id, 'production_date' => '2026-05-01']);
        return [$product, $unit, $location, $lot];
    }

    private function movement(Product $product, Unit $unit, StockLocation $location, ProductionLot $lot, string $date, string $quantity, string $type = 'inventory_adjustment'): StockMovement
    {
        return StockMovement::create(['status' => 'confirmed', 'movement_type' => $type, 'movement_date' => $date, 'product_id' => $product->id, 'stock_location_id' => $location->id, 'unit_id' => $unit->id, 'quantity' => $quantity, 'production_lot_id' => $lot->id, 'lot_code' => $lot->lot_code, 'confirmed_at' => now()]);
    }
}
