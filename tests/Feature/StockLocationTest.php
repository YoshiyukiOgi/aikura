<?php

namespace Tests\Feature;

use App\Models\StockLocation;
use Database\Seeders\StockLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StockLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_locations_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('stock_locations'));

        foreach ([
            'code',
            'name',
            'location_type',
            'parent_stock_location_id',
            'is_default_shipping_location',
            'is_default_receiving_location',
            'is_inventory_managed',
            'is_shippable',
            'is_sellable',
            'is_tax_relevant',
            'postal_code',
            'address1',
            'address2',
            'phone',
            'sort_order',
            'description',
            'is_active',
            'disabled_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('stock_locations', $column),
                "Column [stock_locations.{$column}] does not exist.",
            );
        }
    }

    public function test_stock_location_seed_creates_default_locations(): void
    {
        $this->seed(StockLocationSeeder::class);

        foreach ([
            'main_brewery',
            'cold_storage',
            'shipping_area',
            'storefront',
            'consignment',
            'inspection_hold',
            'disposal_waiting',
            'external',
        ] as $code) {
            $this->assertDatabaseHas('stock_locations', [
                'code' => $code,
                'is_active' => true,
            ]);
        }
    }

    public function test_stock_location_can_have_parent_and_children(): void
    {
        $parent = StockLocation::create([
            'code' => 'warehouse',
            'name' => 'Warehouse',
            'location_type' => 'warehouse',
        ]);

        $child = StockLocation::create([
            'code' => 'warehouse_a',
            'name' => 'Warehouse A',
            'location_type' => 'shelf',
            'parent_stock_location_id' => $parent->id,
        ]);

        $this->assertTrue($child->parent->is($parent));
        $this->assertTrue($parent->children->first()->is($child));
    }

    public function test_stock_location_is_disabled_instead_of_deleted(): void
    {
        $location = StockLocation::create([
            'code' => 'old_storage',
            'name' => 'Old Storage',
            'location_type' => 'warehouse',
        ]);

        $location->update([
            'is_active' => false,
            'disabled_at' => now(),
        ]);

        $this->assertDatabaseHas('stock_locations', [
            'code' => 'old_storage',
            'is_active' => false,
        ]);
        $this->assertNotNull($location->refresh()->disabled_at);
    }
}
