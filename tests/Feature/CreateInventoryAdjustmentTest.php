<?php

namespace Tests\Feature;

use App\Exceptions\Inventory\InventoryAdjustmentException;
use App\Models\Product;
use App\Models\StockLocation;
use App\Models\StockMonthlyBalance;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\Inventory\CreateInventoryAdjustmentData;
use App\Services\Inventory\CreateInventoryAdjustmentService;
use App\Services\Inventory\CurrentStockBalanceService;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\StockLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateInventoryAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_confirmed_inventory_adjustment_movement(): void
    {
        [$product, $unit, $location] = $this->prepareMasterData();

        $movement = app(CreateInventoryAdjustmentService::class)->create(new CreateInventoryAdjustmentData(
            productId: $product->id,
            stockLocationId: $location->id,
            unitId: $unit->id,
            quantity: '2.5000',
            movementDate: '2026-05-23',
            reason: 'opening inventory',
            lotCode: 'LOT-001',
            sourceDocumentNumber: 'ADJ-001',
        ));

        $this->assertSame('confirmed', $movement->status);
        $this->assertSame('inventory_adjustment', $movement->movement_type);
        $this->assertSame('2.5000', $movement->quantity);
        $this->assertSame('opening inventory', $movement->reason);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'stock_movement.inventory_adjusted',
            'target_table' => 'stock_movements',
            'target_id' => (string) $movement->id,
            'reason' => 'opening inventory',
        ]);
    }

    public function test_inventory_adjustment_updates_current_stock_by_movement_history(): void
    {
        [$product, $unit, $location] = $this->prepareMasterData();

        app(CreateInventoryAdjustmentService::class)->create(new CreateInventoryAdjustmentData(
            productId: $product->id,
            stockLocationId: $location->id,
            unitId: $unit->id,
            quantity: '10.0000',
            movementDate: '2026-05-23',
            reason: 'opening inventory',
        ));

        app(CreateInventoryAdjustmentService::class)->create(new CreateInventoryAdjustmentData(
            productId: $product->id,
            stockLocationId: $location->id,
            unitId: $unit->id,
            quantity: '-1.5000',
            movementDate: '2026-05-24',
            reason: 'inventory count difference',
        ));

        $balance = app(CurrentStockBalanceService::class)
            ->forProductLocationUnit($product->id, $location->id, $unit->id);

        $this->assertSame('8.5000', $balance->physicalQuantity);
        $this->assertSame('8.5000', $balance->availableQuantity);
    }

    public function test_it_rejects_zero_quantity_adjustment(): void
    {
        [$product, $unit, $location] = $this->prepareMasterData();

        $this->expectException(InventoryAdjustmentException::class);

        app(CreateInventoryAdjustmentService::class)->create(new CreateInventoryAdjustmentData(
            productId: $product->id,
            stockLocationId: $location->id,
            unitId: $unit->id,
            quantity: '0.0000',
            movementDate: '2026-05-23',
            reason: 'zero adjustment',
        ));
    }

    public function test_it_rejects_empty_reason(): void
    {
        [$product, $unit, $location] = $this->prepareMasterData();

        $this->expectException(InventoryAdjustmentException::class);

        app(CreateInventoryAdjustmentService::class)->create(new CreateInventoryAdjustmentData(
            productId: $product->id,
            stockLocationId: $location->id,
            unitId: $unit->id,
            quantity: '1.0000',
            movementDate: '2026-05-23',
            reason: ' ',
        ));
    }

    public function test_it_rejects_non_inventory_managed_product(): void
    {
        [$product, $unit, $location] = $this->prepareMasterData();
        $product->update(['is_inventory_managed' => false]);

        $this->expectException(InventoryAdjustmentException::class);

        app(CreateInventoryAdjustmentService::class)->create(new CreateInventoryAdjustmentData(
            productId: $product->id,
            stockLocationId: $location->id,
            unitId: $unit->id,
            quantity: '1.0000',
            movementDate: '2026-05-23',
            reason: 'adjustment',
        ));
    }

    public function test_it_rejects_adjustment_in_confirmed_stock_month(): void
    {
        [$product, $unit, $location] = $this->prepareMasterData();
        StockMonthlyBalance::create([
            'status' => 'confirmed',
            'year' => 2026,
            'month' => 5,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'confirmed_at' => now(),
        ]);

        $this->expectException(\App\Exceptions\Inventory\ClosedStockPeriodException::class);

        app(CreateInventoryAdjustmentService::class)->create(new CreateInventoryAdjustmentData(
            productId: $product->id,
            stockLocationId: $location->id,
            unitId: $unit->id,
            quantity: '1.0000',
            movementDate: '2026-05-23',
            reason: 'closed period adjustment',
        ));
    }

    /**
     * @return array{0: Product, 1: Unit, 2: StockLocation}
     */
    private function prepareMasterData(): array
    {
        $this->seed([
            ProductUnitMasterSeeder::class,
            StockLocationSeeder::class,
        ]);

        $unit = Unit::where('code', 'bottle')->firstOrFail();
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();
        $product = Product::create([
            'product_code' => 'adjustment_sake',
            'product_type' => 'sake',
            'name' => 'Adjustment Sake',
            'display_name' => 'Adjustment Sake',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_alcohol' => true,
            'is_inventory_managed' => true,
        ]);

        return [$product, $unit, $location];
    }
}
