<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\Role;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\FoundationPermissionSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\StockLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryLotStockProductTypeFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_lot_stock_can_be_filtered_by_product_type(): void
    {
        [$sakeLot, $kasuLot] = $this->prepareLotStock();

        $response = $this->getJson('/api/v1/inventory/stock?product_type=sake');

        $response->assertOk()
            ->assertJsonPath('data.stock_balances.0.production_lot_id', $sakeLot->id)
            ->assertJsonPath('data.stock_balances.0.product_type', 'sake')
            ->assertJsonPath('data.stock_balances.0.product_type_label', '酒');

        $this->assertCount(1, $response->json('data.stock_balances'));
        $this->assertNotSame($kasuLot->id, $response->json('data.stock_balances.0.production_lot_id'));
    }

    public function test_lot_stock_as_of_can_be_filtered_by_product_type(): void
    {
        [$sakeLot] = $this->prepareLotStock();

        $response = $this->getJson('/api/v1/inventory/lot-stock-as-of?as_of_date=2026-06-30&product_type=sake');

        $response->assertOk()
            ->assertJsonPath('data.lot_stock_balances.0.production_lot_id', $sakeLot->id)
            ->assertJsonPath('data.lot_stock_balances.0.product_type', 'sake')
            ->assertJsonPath('data.lot_stock_balances.0.product_type_label', '酒');

        $this->assertCount(1, $response->json('data.lot_stock_balances'));
    }

    /**
     * @return array{0: ProductionLot, 1: ProductionLot}
     */
    private function prepareLotStock(): array
    {
        $this->seed([FoundationPermissionSeeder::class, ProductUnitMasterSeeder::class, StockLocationSeeder::class]);
        $user = User::query()->create([
            'name' => 'Inventory User',
            'email' => 'inventory-filter@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::query()->where('code', 'admin')->firstOrFail());
        $this->actingAs($user);

        $bottle = Unit::query()->where('code', 'bottle')->firstOrFail();
        $bag = Unit::query()->where('code', 'bag')->firstOrFail();
        $gram = Unit::query()->where('code', 'gram')->firstOrFail();
        $location = StockLocation::query()->where('code', 'main_brewery')->firstOrFail();

        $sake = Product::query()->create([
            'product_code' => 'FILTER-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Filter Sake',
            'display_name' => 'Filter Sake 720ml',
            'base_unit_id' => $bottle->id,
            'inventory_unit_id' => $bottle->id,
            'capacity_value' => '720.0000',
            'capacity_unit_id' => Unit::query()->where('code', 'milliliter')->firstOrFail()->id,
            'alcohol_percentage' => '15.00',
            'is_alcohol' => true,
            'legacy_code' => '1001',
        ]);
        $kasu = Product::query()->create([
            'product_code' => 'FILTER-KASU-001',
            'product_type' => 'kasu',
            'name' => 'Filter Kasu',
            'display_name' => 'Filter Kasu 500g',
            'base_unit_id' => $bag->id,
            'inventory_unit_id' => $bag->id,
            'capacity_value' => '500.0000',
            'capacity_unit_id' => $gram->id,
            'is_alcohol' => false,
            'legacy_code' => '2001',
        ]);
        $sakeLot = $this->createLot('FILTER-SAKE-LOT', $sake, $location, $bottle);
        $kasuLot = $this->createLot('FILTER-KASU-LOT', $kasu, $location, $bag);

        $this->createMovement($sakeLot, $location, $bottle, '10.0000');
        $this->createMovement($kasuLot, $location, $bag, '5.0000');

        return [$sakeLot, $kasuLot];
    }

    private function createLot(string $lotCode, Product $product, StockLocation $location, Unit $unit): ProductionLot
    {
        return ProductionLot::query()->create([
            'lot_code' => $lotCode,
            'display_name' => $product->display_name.' / 初期詳細名',
            'status' => 'active',
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'capacity_value' => $product->capacity_value,
            'capacity_unit_id' => $product->capacity_unit_id,
            'alcohol_percentage' => $product->alcohol_percentage,
            'external_system_code' => 'ITARO-PRODUCT-DETAIL-'.$product->legacy_code.'-1',
            'is_active' => true,
        ]);
    }

    private function createMovement(ProductionLot $lot, StockLocation $location, Unit $unit, string $quantity): void
    {
        StockMovement::query()->create([
            'status' => 'confirmed',
            'movement_type' => 'opening_stock',
            'movement_date' => '2026-06-30',
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'quantity' => $quantity,
            'production_lot_id' => $lot->id,
            'lot_code' => $lot->lot_code,
            'confirmed_at' => now(),
        ]);
    }
}
