<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\StockLotMonthlyBalance;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\Inventory\LotStockBalanceService;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\StockLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LotStockBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_lot_stock_from_confirmed_movements(): void
    {
        [$product, $unit, $location, $lot] = $this->prepareBaseData();

        $this->createMovement($product, $unit, $location, $lot, 'confirmed', '10.0000');
        $this->createMovement($product, $unit, $location, $lot, 'confirmed', '-3.0000');
        $this->createMovement($product, $unit, $location, $lot, 'draft', '99.0000');
        $this->createMovement($product, $unit, $location, $lot, 'cancelled', '50.0000', now());
        $this->createMovement($product, $unit, $location, null, 'confirmed', '100.0000');

        $balance = app(LotStockBalanceService::class)
            ->forLotLocationUnit($lot->id, $location->id, $unit->id);

        $this->assertSame($lot->id, $balance->productionLotId);
        $this->assertSame('7.0000', $balance->physicalQuantity);
        $this->assertSame('0.0000', $balance->reservedQuantity);
        $this->assertSame('0.0000', $balance->allocatedQuantity);
        $this->assertSame('7.0000', $balance->availableQuantity);
    }

    public function test_closed_movements_are_included_in_lot_stock(): void
    {
        [$product, $unit, $location, $lot] = $this->prepareBaseData();

        $this->createMovement($product, $unit, $location, $lot, 'closed', '4.0000');

        $balance = app(LotStockBalanceService::class)
            ->forLotLocationUnit($lot->id, $location->id, $unit->id);

        $this->assertSame('4.0000', $balance->physicalQuantity);
    }

    public function test_it_lists_lot_balances_by_lot_product_location_and_unit(): void
    {
        [$product, $unit, $location, $firstLot] = $this->prepareBaseData();
        $secondLocation = StockLocation::where('code', 'cold_storage')->firstOrFail();
        $secondLot = $this->createLot('LOT-STOCK-002', $product, $secondLocation);

        $this->createMovement($product, $unit, $location, $firstLot, 'confirmed', '10.0000');
        $this->createMovement($product, $unit, $secondLocation, $secondLot, 'confirmed', '2.5000');

        $balances = app(LotStockBalanceService::class)->all();

        $this->assertCount(2, $balances);
        $this->assertSame($firstLot->id, $balances[0]->productionLotId);
        $this->assertSame('10.0000', $balances[0]->physicalQuantity);
        $this->assertSame($secondLot->id, $balances[1]->productionLotId);
        $this->assertSame('2.5000', $balances[1]->physicalQuantity);
    }

    public function test_it_lists_lot_balances_as_of_date(): void
    {
        [$product, $unit, $location, $lot] = $this->prepareBaseData();

        $this->createMovement($product, $unit, $location, $lot, 'confirmed', '10.0000', null, '2026-07-10');
        $this->createMovement($product, $unit, $location, $lot, 'confirmed', '-3.0000', null, '2026-07-20');

        $balances = app(LotStockBalanceService::class)->allAsOf('2026-07-15');

        $this->assertCount(1, $balances);
        $this->assertSame($lot->id, $balances[0]->productionLotId);
        $this->assertSame('10.0000', $balances[0]->physicalQuantity);
    }

    public function test_as_of_date_never_includes_later_movements(): void
    {
        CarbonImmutable::setTestNow('2026-07-30 12:00:00');
        [$product, $unit, $location, $lot] = $this->prepareBaseData();

        $this->createMovement($product, $unit, $location, $lot, 'confirmed', '10.0000', null, '2026-07-29');
        $this->createMovement($product, $unit, $location, $lot, 'confirmed', '10.0000', null, '2026-08-01');
        $this->createMovement($product, $unit, $location, $lot, 'confirmed', '-3.0000', null, '2026-08-10');

        $todayBalances = app(LotStockBalanceService::class)->allAsOf('2026-07-30');
        $futureBalances = app(LotStockBalanceService::class)->allAsOf('2026-08-05');

        $this->assertSame('10.0000', $todayBalances->sole()->physicalQuantity);
        $this->assertSame('20.0000', $futureBalances->sole()->physicalQuantity);
    }

    public function test_as_of_date_ignores_pre_operational_closing_and_uses_opening_stock_plus_following_movements(): void
    {
        CarbonImmutable::setTestNow('2026-07-31 12:00:00');
        [$product, $unit, $location, $lot] = $this->prepareBaseData();
        $openingLot = $this->createLot('OPEN-20260701-DUP', $product, $location);

        $this->createMonthlyBalance($unit, $location, $lot, '2026-06-01', '2026-06-30', '20.0000');
        $this->createMovement($product, $unit, $location, $openingLot, 'confirmed', '20.0000', null, '2026-07-01', 'opening_stock');
        $this->createMovement($product, $unit, $location, $openingLot, 'confirmed', '-3.0000', null, '2026-07-10', 'shipment');
        $this->createMovement($product, $unit, $location, $openingLot, 'confirmed', '-2.0000', null, '2026-07-20', 'non_sales_breakage');
        $this->createMovement($product, $unit, $location, $openingLot, 'confirmed', '1.0000', null, '2026-07-25', 'sales_return');
        $this->createMovement($product, $unit, $location, $openingLot, 'confirmed', '-99.0000', null, '2026-08-01', 'shipment');

        $julyFirst = app(LotStockBalanceService::class)->allAsOf('2026-07-01');
        $julyEnd = app(LotStockBalanceService::class)->allAsOf('2026-07-31');

        $this->assertSame('20.0000', $julyFirst->sole()->physicalQuantity);
        $this->assertSame('16.0000', $julyEnd->sole()->physicalQuantity);
        $this->assertSame($openingLot->id, $julyEnd->sole()->productionLotId);
        $this->assertFalse($julyEnd->pluck('productionLotId')->contains($lot->id));
    }

    /**
     * @return array{0: Product, 1: Unit, 2: StockLocation, 3: ProductionLot}
     */
    private function prepareBaseData(): array
    {
        $this->seed([
            ProductUnitMasterSeeder::class,
            StockLocationSeeder::class,
        ]);

        $unit = Unit::where('code', 'bottle')->firstOrFail();
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();

        $product = Product::create([
            'product_code' => 'LOT-STOCK-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Lot Stock Sake',
            'display_name' => 'Lot Stock Sake 720ml',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_alcohol' => true,
            'is_inventory_managed' => true,
        ]);

        $lot = $this->createLot('LOT-STOCK-001', $product, $location);

        return [$product, $unit, $location, $lot];
    }

    private function createLot(string $lotCode, Product $product, StockLocation $location): ProductionLot
    {
        return ProductionLot::create([
            'lot_code' => $lotCode,
            'display_name' => $lotCode.' Display',
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'production_date' => '2026-06-01',
        ]);
    }

    private function createMovement(
        Product $product,
        Unit $unit,
        StockLocation $location,
        ?ProductionLot $lot,
        string $status,
        string $quantity,
        mixed $cancelledAt = null,
        string $movementDate = '2026-07-15',
        string $movementType = 'inventory_adjustment',
    ): StockMovement {
        return StockMovement::create([
            'status' => $status,
            'movement_type' => $movementType,
            'movement_date' => $movementDate,
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'quantity' => $quantity,
            'production_lot_id' => $lot?->id,
            'lot_code' => $lot?->lot_code,
            'cancelled_at' => $cancelledAt,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
            'closed_at' => $status === 'closed' ? now() : null,
        ]);
    }

    private function createMonthlyBalance(
        Unit $unit,
        StockLocation $location,
        ProductionLot $lot,
        string $periodStart,
        string $periodEnd,
        string $quantity,
    ): StockLotMonthlyBalance {
        return StockLotMonthlyBalance::create([
            'status' => 'confirmed',
            'year' => (int) substr($periodStart, 0, 4),
            'month' => (int) substr($periodStart, 5, 2),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'production_lot_id' => $lot->id,
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'closing_quantity' => $quantity,
            'calculated_at' => now(),
            'confirmed_at' => now(),
        ]);
    }
}
