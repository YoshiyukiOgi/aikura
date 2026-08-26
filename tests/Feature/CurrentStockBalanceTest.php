<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\Inventory\CurrentStockBalanceService;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\StockLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentStockBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_current_stock_from_confirmed_movements(): void
    {
        [$product, $unit, $location, $lot] = $this->prepareMasterData();

        $this->createMovement($lot, $location, 'confirmed', '10.0000', '2026-07-23');
        $this->createMovement($lot, $location, 'confirmed', '-3.0000', '2026-07-24');
        $this->createMovement($lot, $location, 'draft', '99.0000', '2026-07-25');
        $this->createMovement($lot, $location, 'cancelled', '50.0000', '2026-07-26', now());

        $balance = app(CurrentStockBalanceService::class)
            ->forProductLocationUnit($product->id, $location->id, $unit->id);

        $this->assertSame('7.0000', $balance->physicalQuantity);
        $this->assertSame('0.0000', $balance->reservedQuantity);
        $this->assertSame('0.0000', $balance->allocatedQuantity);
        $this->assertSame('7.0000', $balance->availableQuantity);
    }

    public function test_closed_movements_are_included_in_current_stock(): void
    {
        [$product, $unit, $location, $lot] = $this->prepareMasterData();

        $this->createMovement($lot, $location, 'closed', '4.0000', '2026-07-23');

        $balance = app(CurrentStockBalanceService::class)
            ->forProductLocationUnit($product->id, $location->id, $unit->id);

        $this->assertSame('4.0000', $balance->physicalQuantity);
    }

    public function test_it_lists_balances_by_product_location_and_unit(): void
    {
        [$product, $unit, $location, $lot] = $this->prepareMasterData();
        $secondLocation = StockLocation::where('code', 'cold_storage')->firstOrFail();
        $secondLot = ProductionLot::create([
            'lot_code' => 'BALANCE-LOT-002',
            'display_name' => 'Balance Lot 002',
            'status' => 'active',
            'stock_location_id' => $secondLocation->id,
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);

        $this->createMovement($lot, $location, 'confirmed', '10.0000', '2026-07-23');
        $this->createMovement($secondLot, $secondLocation, 'confirmed', '2.5000', '2026-07-23');

        $balances = app(CurrentStockBalanceService::class)->all();

        $this->assertCount(2, $balances);
        $this->assertSame('10.0000', $balances[0]->physicalQuantity);
        $this->assertSame('2.5000', $balances[1]->physicalQuantity);
    }

    /**
     * @return array{0: Product, 1: Unit, 2: StockLocation, 3: ProductionLot}
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
            'product_code' => 'balance_sake',
            'product_type' => 'sake',
            'name' => 'Balance Sake',
            'display_name' => 'Balance Sake',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_alcohol' => true,
            'is_inventory_managed' => true,
        ]);

        $lot = ProductionLot::create([
            'lot_code' => 'BALANCE-LOT-001',
            'display_name' => 'Balance Lot 001',
            'status' => 'active',
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);

        return [$product, $unit, $location, $lot];
    }

    private function createMovement(
        ProductionLot $lot,
        StockLocation $location,
        string $status,
        string $quantity,
        string $movementDate,
        mixed $cancelledAt = null,
    ): StockMovement {
        return StockMovement::create([
            'status' => $status,
            'movement_type' => 'inventory_adjustment',
            'movement_date' => $movementDate,
            'production_lot_id' => $lot->id,
            'lot_code' => $lot->lot_code,
            'stock_location_id' => $location->id,
            'unit_id' => $lot->unit_id,
            'quantity' => $quantity,
            'cancelled_at' => $cancelledAt,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
            'closed_at' => $status === 'closed' ? now() : null,
        ]);
    }
}
