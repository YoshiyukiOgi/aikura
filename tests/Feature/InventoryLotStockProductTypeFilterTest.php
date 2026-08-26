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

        $response = $this->getJson('/api/v1/inventory/lot-stock-as-of?as_of_date=2026-07-30&product_type=sake');

        $response->assertOk()
            ->assertJsonPath('data.lot_stock_balances.0.production_lot_id', $sakeLot->id)
            ->assertJsonPath('data.lot_stock_balances.0.product_type', 'sake')
            ->assertJsonPath('data.lot_stock_balances.0.product_type_label', '酒');

        $this->assertCount(1, $response->json('data.lot_stock_balances'));
    }

    public function test_lot_stock_as_of_search_normalizes_spaces_and_full_width_digits(): void
    {
        $this->prepareLotStock();

        $bottle = Unit::query()->where('code', 'bottle')->firstOrFail();
        $milliliter = Unit::query()->where('code', 'milliliter')->firstOrFail();
        $location = StockLocation::query()->where('code', 'main_brewery')->firstOrFail();

        $product = Product::query()->create([
            'product_code' => 'FILTER-AKI-720',
            'product_type' => 'sake',
            'name' => '安芸虎 純米吟醸',
            'display_name' => '安芸虎 純米吟醸 720ml',
            'base_unit_id' => $bottle->id,
            'inventory_unit_id' => $bottle->id,
            'capacity_value' => '720.0000',
            'capacity_unit_id' => $milliliter->id,
            'alcohol_percentage' => '16.00',
            'is_alcohol' => true,
            'legacy_code' => '3001',
        ]);
        $lot = $this->createLot('FILTER-AKI-720-LOT', $product, $location, $bottle);
        $this->createMovement($lot, $location, $bottle, '12.0000');

        $this->getJson('/api/v1/inventory/lot-stock-as-of?as_of_date=2026-07-30&q=' . rawurlencode('安芸虎　純米吟醸'))
            ->assertOk()
            ->assertJsonPath('data.lot_stock_balances.0.production_lot_id', $lot->id)
            ->assertJsonPath('data.lot_stock_balances.0.product_code', 'FILTER-AKI-720');

        $this->getJson('/api/v1/inventory/lot-stock-as-of?as_of_date=2026-07-30&q=' . rawurlencode('安芸虎　７２０'))
            ->assertOk()
            ->assertJsonPath('data.lot_stock_balances.0.production_lot_id', $lot->id)
            ->assertJsonPath('data.lot_stock_balances.0.product_code', 'FILTER-AKI-720');

        $this->getJson('/api/v1/inventory/lot-stock-as-of?as_of_date=2026-07-30&q=' . rawurlencode('安芸虎 720'))
            ->assertOk()
            ->assertJsonPath('data.lot_stock_balances.0.production_lot_id', $lot->id)
            ->assertJsonPath('data.lot_stock_balances.0.product_code', 'FILTER-AKI-720');
    }

    public function test_inventory_pages_default_to_sake_and_render_compact_lot_capacity(): void
    {
        $this->prepareLotStock();

        $inventory = $this->get('/inventory');

        $inventory->assertOk()
            ->assertSee('<option value="sake" selected>酒</option>', false)
            ->assertSee('title="${lotHover(r.lot_name,r.lot_code)}"', false)
            ->assertSee('${wholeNumber(r.capacity_value)}', false)
            ->assertSee("document.getElementById('stock-as-of-date').onchange=()=>pulseButton('refresh-stock')", false);

        $lotStockAsOf = $this->get('/inventory/lot-stock-as-of');

        $lotStockAsOf->assertOk()
            ->assertSee('productType.value = "sake"', false)
            ->assertSee('class="lot-cell"', false)
            ->assertDontSee('${esc(row.product_name)}</td>', false);
    }

    public function test_lot_stock_rows_are_sorted_by_type_lot_name_capacity_date_lot_and_location(): void
    {
        [$sakeLot, $kasuLot] = $this->prepareLotStock();
        $location = StockLocation::query()->where('code', 'main_brewery')->firstOrFail();
        $bottle = Unit::query()->where('code', 'bottle')->firstOrFail();
        $milliliter = Unit::query()->where('code', 'milliliter')->firstOrFail();

        $alpha720 = Product::query()->create([
            'product_code' => 'SORT-SAKE-A-720',
            'product_type' => 'sake',
            'name' => 'Alpha Sake',
            'display_name' => 'Alpha Sake 720ml',
            'base_unit_id' => $bottle->id,
            'inventory_unit_id' => $bottle->id,
            'capacity_value' => '720.0000',
            'capacity_unit_id' => $milliliter->id,
            'is_alcohol' => true,
            'legacy_code' => '3001',
        ]);
        $alpha1800 = Product::query()->create([
            'product_code' => 'SORT-SAKE-A-1800',
            'product_type' => 'sake',
            'name' => 'Alpha Sake',
            'display_name' => 'Alpha Sake 1800ml',
            'base_unit_id' => $bottle->id,
            'inventory_unit_id' => $bottle->id,
            'capacity_value' => '1800.0000',
            'capacity_unit_id' => $milliliter->id,
            'is_alcohol' => true,
            'legacy_code' => '3002',
        ]);

        $old720 = ProductionLot::query()->create([
            'lot_code' => 'SORT-A-720-OLD',
            'display_name' => 'Alpha Lot B 720ml',
            'status' => 'active',
            'stock_location_id' => $location->id,
            'unit_id' => $bottle->id,
            'capacity_value' => $alpha720->capacity_value,
            'capacity_unit_id' => $alpha720->capacity_unit_id,
            'production_date' => '2026-05-01',
            'external_system_code' => 'ITARO-PRODUCT-DETAIL-3001-1',
            'is_active' => true,
        ]);
        $new720 = ProductionLot::query()->create([
            'lot_code' => 'SORT-A-720-NEW',
            'display_name' => 'Alpha Lot A 720ml',
            'status' => 'active',
            'stock_location_id' => $location->id,
            'unit_id' => $bottle->id,
            'capacity_value' => $alpha720->capacity_value,
            'capacity_unit_id' => $alpha720->capacity_unit_id,
            'production_date' => '2026-06-01',
            'external_system_code' => 'ITARO-PRODUCT-DETAIL-3001-2',
            'is_active' => true,
        ]);
        $large = ProductionLot::query()->create([
            'lot_code' => 'SORT-A-1800',
            'display_name' => 'Alpha Lot C 1800ml',
            'status' => 'active',
            'stock_location_id' => $location->id,
            'unit_id' => $bottle->id,
            'capacity_value' => $alpha1800->capacity_value,
            'capacity_unit_id' => $alpha1800->capacity_unit_id,
            'production_date' => '2026-04-01',
            'external_system_code' => 'ITARO-PRODUCT-DETAIL-3002-1',
            'is_active' => true,
        ]);

        foreach ([$old720, $new720, $large] as $lot) {
            $this->createMovement($lot, $location, $bottle, '1.0000');
        }

        $stockCodes = collect($this->getJson('/api/v1/inventory/stock')->assertOk()->json('data.stock_balances'))
            ->pluck('lot_code')
            ->all();
        $asOfCodes = collect($this->getJson('/api/v1/inventory/lot-stock-as-of?as_of_date=2026-07-30')->assertOk()->json('data.lot_stock_balances'))
            ->pluck('lot_code')
            ->all();

        $expectedRelativeOrder = ['SORT-A-720-NEW', 'SORT-A-720-OLD', 'SORT-A-1800'];
        $this->assertSame($expectedRelativeOrder, array_values(array_intersect($stockCodes, $expectedRelativeOrder)));
        $this->assertSame($expectedRelativeOrder, array_values(array_intersect($asOfCodes, $expectedRelativeOrder)));
        $this->assertLessThan(array_search($kasuLot->lot_code, $stockCodes, true), array_search($sakeLot->lot_code, $stockCodes, true));
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
            'movement_date' => '2026-07-30',
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'quantity' => $quantity,
            'production_lot_id' => $lot->id,
            'lot_code' => $lot->lot_code,
            'confirmed_at' => now(),
        ]);
    }
}
