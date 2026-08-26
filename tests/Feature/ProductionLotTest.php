<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Unit;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\StockLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductionLotTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_lots_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('production_lots'));

        foreach ([
            'lot_code',
            'display_name',
            'status',
            'product_id',
            'stock_location_id',
            'production_date',
            'bottling_date',
            'best_before_date',
            'tank_code',
            'rice_variety',
            'rice_polishing_ratio',
            'production_method',
            'storage_condition',
            'external_system_code',
            'legacy_lot_text',
            'search_key',
            'is_active',
            'disabled_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('production_lots', $column),
                "Column [production_lots.{$column}] does not exist.",
            );
        }

        $this->assertTrue(Schema::hasColumn('stock_movements', 'production_lot_id'));
    }

    public function test_it_creates_production_lot_and_relates_to_product_location_and_stock_movements(): void
    {
        [$product, $unit, $location] = $this->prepareBaseData();

        $lot = ProductionLot::create([
            'lot_code' => 'LOT-2026-0001',
            'display_name' => '2026 Jun Tank A',
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'production_date' => '2026-06-01',
            'bottling_date' => '2026-06-15',
            'best_before_date' => '2027-06-15',
            'tank_code' => 'TANK-A',
            'rice_variety' => 'Yamada Nishiki',
            'rice_polishing_ratio' => '55.00',
            'production_method' => 'junmai_ginjo',
            'storage_condition' => 'refrigerated',
            'external_system_code' => 'BREW-LOT-0001',
            'legacy_lot_text' => 'old lot text',
        ]);

        $movement = StockMovement::create([
            'status' => 'confirmed',
            'movement_type' => 'production_receipt',
            'movement_date' => '2026-06-15',
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'quantity' => '120.0000',
            'production_lot_id' => $lot->id,
            'lot_code' => 'LOT-2026-0001',
            'confirmed_at' => now(),
        ]);

        $lot = $lot->refresh();

        $this->assertSame('LOT-2026-0001', $lot->lot_code);
        $this->assertSame('55.00', $lot->rice_polishing_ratio);
        $this->assertTrue($lot->is_active);
        $this->assertSame($product->id, $lot->product->id);
        $this->assertSame($location->id, $lot->stockLocation->id);
        $this->assertSame($lot->id, $movement->productionLot->id);
        $this->assertSame($movement->id, $lot->stockMovements->first()->id);
        $this->assertSame($lot->id, $product->productionLots->first()->id);
        $this->assertSame($lot->id, $location->productionLots->first()->id);
    }

    public function test_lot_code_is_unique(): void
    {
        [$product, , $location] = $this->prepareBaseData();

        ProductionLot::create([
            'lot_code' => 'LOT-UNIQUE-001',
            'display_name' => 'Unique Lot',
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        ProductionLot::create([
            'lot_code' => 'LOT-UNIQUE-001',
            'display_name' => 'Duplicate Lot',
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
        ]);
    }

    /**
     * @return array{0: Product, 1: Unit, 2: StockLocation}
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
            'product_code' => 'LOT-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Lot Sake',
            'display_name' => 'Lot Sake 720ml',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_alcohol' => true,
            'is_inventory_managed' => true,
        ]);

        return [$product, $unit, $location];
    }
}
