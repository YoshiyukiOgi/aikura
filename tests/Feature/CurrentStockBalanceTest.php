<?php

namespace Tests\Feature;

use App\Models\Product;
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
        [$product, $unit, $location] = $this->prepareMasterData();

        $this->createMovement($product, $unit, $location, 'confirmed', '10.0000');
        $this->createMovement($product, $unit, $location, 'confirmed', '-3.0000');
        $this->createMovement($product, $unit, $location, 'draft', '99.0000');
        $this->createMovement($product, $unit, $location, 'cancelled', '50.0000', now());

        $balance = app(CurrentStockBalanceService::class)
            ->forProductLocationUnit($product->id, $location->id, $unit->id);

        $this->assertSame('7.0000', $balance->physicalQuantity);
        $this->assertSame('0.0000', $balance->reservedQuantity);
        $this->assertSame('0.0000', $balance->allocatedQuantity);
        $this->assertSame('7.0000', $balance->availableQuantity);
    }

    public function test_closed_movements_are_included_in_current_stock(): void
    {
        [$product, $unit, $location] = $this->prepareMasterData();

        $this->createMovement($product, $unit, $location, 'closed', '4.0000');

        $balance = app(CurrentStockBalanceService::class)
            ->forProductLocationUnit($product->id, $location->id, $unit->id);

        $this->assertSame('4.0000', $balance->physicalQuantity);
    }

    public function test_it_lists_balances_by_product_location_and_unit(): void
    {
        [$product, $unit, $location] = $this->prepareMasterData();
        $secondLocation = StockLocation::where('code', 'cold_storage')->firstOrFail();

        $this->createMovement($product, $unit, $location, 'confirmed', '10.0000');
        $this->createMovement($product, $unit, $secondLocation, 'confirmed', '2.5000');

        $balances = app(CurrentStockBalanceService::class)->all();

        $this->assertCount(2, $balances);
        $this->assertSame('10.0000', $balances[0]->physicalQuantity);
        $this->assertSame('2.5000', $balances[1]->physicalQuantity);
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

        return [$product, $unit, $location];
    }

    private function createMovement(
        Product $product,
        Unit $unit,
        StockLocation $location,
        string $status,
        string $quantity,
        mixed $cancelledAt = null,
    ): StockMovement {
        return StockMovement::create([
            'status' => $status,
            'movement_type' => 'inventory_adjustment',
            'movement_date' => '2026-05-23',
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'quantity' => $quantity,
            'cancelled_at' => $cancelledAt,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
            'closed_at' => $status === 'closed' ? now() : null,
        ]);
    }
}
