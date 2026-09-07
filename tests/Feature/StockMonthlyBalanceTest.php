<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\ProductionLot;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\Inventory\CreateStockMonthlyBalanceDraftService;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\StockLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StockMonthlyBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_lot_monthly_balances_table_exists_without_a_product_link(): void
    {
        $this->assertTrue(Schema::hasTable('stock_lot_monthly_balances'));

        foreach ([
            'status', 'year', 'month', 'period_start', 'period_end', 'production_lot_id',
            'stock_location_id', 'unit_id', 'closing_quantity', 'calculated_at', 'confirmed_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('stock_lot_monthly_balances', $column),
                "Column [stock_lot_monthly_balances.{$column}] does not exist.",
            );
        }

        $this->assertFalse(Schema::hasColumn('stock_lot_monthly_balances', 'product_id'));
        $this->assertFalse(Schema::hasTable('stock_monthly_balances'));
    }

    public function test_it_creates_a_lot_monthly_balance_from_stock_movements(): void
    {
        [$lot, $unit, $location] = $this->prepareMasterData();

        $this->createMovement($lot, $unit, $location, 'production_receipt', '2026-04-30', '10.0000');
        $this->createMovement($lot, $unit, $location, 'production_receipt', '2026-05-01', '5.0000');
        $this->createMovement($lot, $unit, $location, 'shipment', '2026-05-02', '-3.0000');
        $this->createMovement($lot, $unit, $location, 'inventory_adjustment', '2026-05-03', '-1.0000');
        $this->createMovement($lot, $unit, $location, 'production_receipt', '2026-06-01', '99.0000');

        $balances = app(CreateStockMonthlyBalanceDraftService::class)
            ->create(2026, 5, 'monthly lot stock calculation');

        $this->assertCount(1, $balances);
        $balance = $balances->first();

        $this->assertSame('draft', $balance->status);
        $this->assertSame(2026, $balance->year);
        $this->assertSame(5, $balance->month);
        $this->assertSame($lot->id, $balance->production_lot_id);
        $this->assertSame('2026-05-01', $balance->period_start->toDateString());
        $this->assertSame('2026-05-31', $balance->period_end->toDateString());
        $this->assertSame('11.0000', $balance->closing_quantity);
        $this->assertNotNull($balance->calculated_at);
    }

    public function test_it_excludes_draft_and_cancelled_lot_movements(): void
    {
        [$lot, $unit, $location] = $this->prepareMasterData();

        $this->createMovement($lot, $unit, $location, 'production_receipt', '2026-05-01', '5.0000');
        $this->createMovement($lot, $unit, $location, 'production_receipt', '2026-05-02', '99.0000', 'draft');
        $this->createMovement($lot, $unit, $location, 'production_receipt', '2026-05-03', '88.0000', 'cancelled', now());

        $balance = app(CreateStockMonthlyBalanceDraftService::class)
            ->create(2026, 5)
            ->first();

        $this->assertSame('5.0000', $balance->closing_quantity);
    }

    public function test_it_updates_the_existing_draft_for_the_same_lot_month_key(): void
    {
        [$lot, $unit, $location] = $this->prepareMasterData();

        $this->createMovement($lot, $unit, $location, 'production_receipt', '2026-05-01', '5.0000');
        $first = app(CreateStockMonthlyBalanceDraftService::class)->create(2026, 5)->first();

        $this->createMovement($lot, $unit, $location, 'production_receipt', '2026-05-02', '2.0000');
        $second = app(CreateStockMonthlyBalanceDraftService::class)->create(2026, 5)->first();

        $this->assertSame($first->id, $second->id);
        $this->assertSame('7.0000', $second->closing_quantity);
    }

    /**
     * @return array{0: ProductionLot, 1: Unit, 2: StockLocation}
     */
    private function prepareMasterData(): array
    {
        $this->seed([
            ProductUnitMasterSeeder::class,
            StockLocationSeeder::class,
        ]);
        AppSetting::setValue('operational_start_date', '2026-04-01');

        $unit = Unit::where('code', 'bottle')->firstOrFail();
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();
        $lot = ProductionLot::create([
            'lot_code' => 'MONTHLY-LOT-001',
            'display_name' => 'Monthly Lot',
            'status' => 'active',
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);

        return [$lot, $unit, $location];
    }

    private function createMovement(
        ProductionLot $lot,
        Unit $unit,
        StockLocation $location,
        string $movementType,
        string $movementDate,
        string $quantity,
        string $status = 'confirmed',
        mixed $cancelledAt = null,
    ): StockMovement {
        return StockMovement::create([
            'status' => $status,
            'movement_type' => $movementType,
            'movement_date' => $movementDate,
            'production_lot_id' => $lot->id,
            'lot_code' => $lot->lot_code,
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'quantity' => $quantity,
            'cancelled_at' => $cancelledAt,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
        ]);
    }
}
