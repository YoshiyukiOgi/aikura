<?php

namespace Tests\Feature;

use App\Exceptions\Inventory\StockMonthlyBalanceConfirmationException;
use App\Models\Product;
use App\Models\StockLocation;
use App\Models\StockMonthlyBalance;
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
        [$product, $unit, $location] = $this->prepareMasterData();

        $april = $this->createMovement($product, $unit, $location, 'production_receipt', '2026-04-30', '10.0000');
        $mayInbound = $this->createMovement($product, $unit, $location, 'production_receipt', '2026-05-01', '5.0000');
        $mayOutbound = $this->createMovement($product, $unit, $location, 'shipment', '2026-05-02', '-3.0000');
        $june = $this->createMovement($product, $unit, $location, 'production_receipt', '2026-06-01', '99.0000');

        app(CreateStockMonthlyBalanceDraftService::class)->create(2026, 5);

        $balances = app(ConfirmStockMonthlyBalanceService::class)
            ->confirm(2026, 5, 'monthly stock close');

        $this->assertCount(1, $balances);
        $this->assertSame('confirmed', $balances->first()->status);
        $this->assertNotNull($balances->first()->confirmed_at);

        $this->assertSame('confirmed', $april->refresh()->status);
        $this->assertSame('closed', $mayInbound->refresh()->status);
        $this->assertSame('closed', $mayOutbound->refresh()->status);
        $this->assertSame('confirmed', $june->refresh()->status);
        $this->assertNotNull($mayInbound->closed_at);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'stock_monthly_balance.confirmed',
            'target_table' => 'stock_monthly_balances',
            'target_id' => '2026-05',
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
        [$product, $unit, $location] = $this->prepareMasterData();

        $this->createMovement($product, $unit, $location, 'production_receipt', '2026-05-01', '5.0000');
        app(CreateStockMonthlyBalanceDraftService::class)->create(2026, 5);

        $this->expectException(StockMonthlyBalanceConfirmationException::class);

        app(ConfirmStockMonthlyBalanceService::class)->confirm(2026, 5, ' ');
    }

    public function test_it_rejects_already_confirmed_month(): void
    {
        [$product, $unit, $location] = $this->prepareMasterData();

        $this->createMovement($product, $unit, $location, 'production_receipt', '2026-05-01', '5.0000');
        app(CreateStockMonthlyBalanceDraftService::class)->create(2026, 5);
        StockMonthlyBalance::query()->where('year', 2026)->where('month', 5)->update(['status' => 'confirmed']);

        $this->expectException(StockMonthlyBalanceConfirmationException::class);

        app(ConfirmStockMonthlyBalanceService::class)->confirm(2026, 5, 'monthly stock close');
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

        return [$product, $unit, $location];
    }

    private function createMovement(
        Product $product,
        Unit $unit,
        StockLocation $location,
        string $movementType,
        string $movementDate,
        string $quantity,
    ): StockMovement {
        return StockMovement::create([
            'status' => 'confirmed',
            'movement_type' => $movementType,
            'movement_date' => $movementDate,
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'quantity' => $quantity,
            'confirmed_at' => now(),
        ]);
    }
}
