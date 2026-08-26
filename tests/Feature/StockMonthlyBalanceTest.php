<?php

namespace Tests\Feature;

use App\Models\Product;
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

    public function test_stock_monthly_balances_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('stock_monthly_balances'));

        foreach ([
            'status',
            'year',
            'month',
            'period_start',
            'period_end',
            'stock_location_id',
            'unit_id',
            'opening_quantity',
            'inbound_quantity',
            'outbound_quantity',
            'adjustment_quantity',
            'closing_quantity',
            'calculated_at',
            'confirmed_at',
            'closed_at',
            'reason',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('stock_monthly_balances', $column),
                "Column [stock_monthly_balances.{$column}] does not exist.",
            );
        }
    }

    public function test_it_creates_monthly_balance_draft_from_stock_movements(): void
    {
        [$product, $unit, $location] = $this->prepareMasterData();

        $this->createMovement($product, $unit, $location, 'production_receipt', '2026-04-30', '10.0000');
        $this->createMovement($product, $unit, $location, 'production_receipt', '2026-05-01', '5.0000');
        $this->createMovement($product, $unit, $location, 'shipment', '2026-05-02', '-3.0000');
        $this->createMovement($product, $unit, $location, 'inventory_adjustment', '2026-05-03', '-1.0000');
        $this->createMovement($product, $unit, $location, 'production_receipt', '2026-06-01', '99.0000');

        $balances = app(CreateStockMonthlyBalanceDraftService::class)
            ->create(2026, 5, 'monthly stock calculation');

        $this->assertCount(1, $balances);
        $balance = $balances->first();

        $this->assertSame('draft', $balance->status);
        $this->assertSame(2026, $balance->year);
        $this->assertSame(5, $balance->month);
        $this->assertSame('2026-05-01', $balance->period_start->toDateString());
        $this->assertSame('2026-05-31', $balance->period_end->toDateString());
        $this->assertSame('10.0000', $balance->opening_quantity);
        $this->assertSame('5.0000', $balance->inbound_quantity);
        $this->assertSame('-3.0000', $balance->outbound_quantity);
        $this->assertSame('-1.0000', $balance->adjustment_quantity);
        $this->assertSame('11.0000', $balance->closing_quantity);
        $this->assertNotNull($balance->calculated_at);
    }

    public function test_it_excludes_draft_and_cancelled_movements(): void
    {
        [$product, $unit, $location] = $this->prepareMasterData();

        $this->createMovement($product, $unit, $location, 'production_receipt', '2026-05-01', '5.0000');
        $this->createMovement($product, $unit, $location, 'production_receipt', '2026-05-02', '99.0000', 'draft');
        $this->createMovement($product, $unit, $location, 'production_receipt', '2026-05-03', '88.0000', 'cancelled', now());

        $balance = app(CreateStockMonthlyBalanceDraftService::class)
            ->create(2026, 5)
            ->first();

        $this->assertSame('5.0000', $balance->closing_quantity);
    }

    public function test_it_updates_existing_draft_for_same_month_key(): void
    {
        [$product, $unit, $location] = $this->prepareMasterData();

        $this->createMovement($product, $unit, $location, 'production_receipt', '2026-05-01', '5.0000');
        $first = app(CreateStockMonthlyBalanceDraftService::class)->create(2026, 5)->first();

        $this->createMovement($product, $unit, $location, 'production_receipt', '2026-05-02', '2.0000');
        $second = app(CreateStockMonthlyBalanceDraftService::class)->create(2026, 5)->first();

        $this->assertSame($first->id, $second->id);
        $this->assertSame('7.0000', $second->closing_quantity);
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
            'product_code' => 'monthly_sake',
            'product_type' => 'sake',
            'name' => 'Monthly Sake',
            'display_name' => 'Monthly Sake',
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
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'quantity' => $quantity,
            'cancelled_at' => $cancelledAt,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
        ]);
    }
}
