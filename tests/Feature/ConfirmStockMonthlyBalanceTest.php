<?php

namespace Tests\Feature;

use App\Exceptions\Inventory\StockMonthlyBalanceConfirmationException;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\StockLocation;
use App\Models\StockLotMonthlyBalance;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\Inventory\ConfirmStockMonthlyBalanceService;
use App\Services\Inventory\CreateStockMonthlyBalanceDraftService;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\StockLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfirmStockMonthlyBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_confirms_monthly_stock_balances_and_closes_period_movements(): void
    {
        [$product, $unit, $location, $lot] = $this->prepareMasterData();

        $julyInbound = $this->createMovement($lot, $location, 'production_receipt', '2026-07-01', '10.0000');
        $julyAdjustment = $this->createMovement($lot, $location, 'production_receipt', '2026-07-02', '5.0000');
        $julyOutbound = $this->createMovement($lot, $location, 'shipment', '2026-07-03', '-3.0000');
        $august = $this->createMovement($lot, $location, 'production_receipt', '2026-08-01', '99.0000');

        app(CreateStockMonthlyBalanceDraftService::class)->create(2026, 7);

        $balances = app(ConfirmStockMonthlyBalanceService::class)
            ->confirm(2026, 7, 'monthly stock close');

        $this->assertCount(1, $balances);
        $this->assertSame('confirmed', $balances->first()->status);
        $this->assertNotNull($balances->first()->confirmed_at);

        $this->assertSame('closed', $julyInbound->refresh()->status);
        $this->assertSame('closed', $julyAdjustment->refresh()->status);
        $this->assertSame('closed', $julyOutbound->refresh()->status);
        $this->assertSame('confirmed', $august->refresh()->status);
        $this->assertNotNull($julyInbound->closed_at);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'stock_lot_monthly_balance.confirmed',
            'target_table' => 'stock_lot_monthly_balances',
            'target_id' => '2026-07',
            'reason' => 'monthly stock close',
        ]);
    }

    public function test_it_rejects_confirmation_without_draft_balances(): void
    {
        $this->expectException(StockMonthlyBalanceConfirmationException::class);

        app(ConfirmStockMonthlyBalanceService::class)->confirm(2026, 5, 'monthly stock close');
    }

    public function test_it_rejects_empty_reason(): void
    {
        [$product, $unit, $location, $lot] = $this->prepareMasterData();

        $this->createMovement($lot, $location, 'production_receipt', '2026-07-01', '5.0000');
        app(CreateStockMonthlyBalanceDraftService::class)->create(2026, 7);

        $this->expectException(StockMonthlyBalanceConfirmationException::class);

        app(ConfirmStockMonthlyBalanceService::class)->confirm(2026, 7, ' ');
    }

    public function test_it_rejects_already_confirmed_month(): void
    {
        [$product, $unit, $location, $lot] = $this->prepareMasterData();

        $this->createMovement($lot, $location, 'production_receipt', '2026-07-01', '5.0000');
        app(CreateStockMonthlyBalanceDraftService::class)->create(2026, 7);
        StockLotMonthlyBalance::query()->where('year', 2026)->where('month', 7)->update(['status' => 'confirmed']);

        $this->expectException(StockMonthlyBalanceConfirmationException::class);

        app(ConfirmStockMonthlyBalanceService::class)->confirm(2026, 7, 'monthly stock close');
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
            'product_code' => 'confirm_monthly_sake',
            'product_type' => 'sake',
            'name' => 'Confirm Monthly Sake',
            'display_name' => 'Confirm Monthly Sake',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_alcohol' => true,
            'is_inventory_managed' => true,
        ]);

        $lot = ProductionLot::create([
            'lot_code' => 'MONTHLY-LOT-001',
            'display_name' => 'Monthly Lot',
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
        string $movementType,
        string $movementDate,
        string $quantity,
    ): StockMovement {
        return StockMovement::create([
            'status' => 'confirmed',
            'movement_type' => $movementType,
            'movement_date' => $movementDate,
            'production_lot_id' => $lot->id,
            'lot_code' => $lot->lot_code,
            'stock_location_id' => $location->id,
            'unit_id' => $lot->unit_id,
            'quantity' => $quantity,
            'confirmed_at' => now(),
        ]);
    }
}
