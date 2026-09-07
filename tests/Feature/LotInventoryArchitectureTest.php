<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\Inventory\ProductLotCandidateSummaryService;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\StockLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LotInventoryArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_tables_do_not_persist_product_links(): void
    {
        $this->assertFalse(Schema::hasTable('product_production_lot'));
        $this->assertFalse(Schema::hasTable('stock_monthly_balances'));
        $this->assertFalse(Schema::hasTable('shipment_stock_reservations'));
        $this->assertFalse(Schema::hasColumn('production_lots', 'product_id'));
        $this->assertFalse(Schema::hasColumn('stock_movements', 'product_id'));
        $this->assertFalse(Schema::hasColumn('stock_lot_monthly_balances', 'product_id'));
    }

    public function test_out_of_range_lot_remains_an_approval_candidate_without_a_product_link(): void
    {
        $this->seed([ProductUnitMasterSeeder::class, StockLocationSeeder::class]);
        $bottle = Unit::where('code', 'bottle')->firstOrFail();
        $milliliter = Unit::where('code', 'milliliter')->firstOrFail();
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();

        $product = Product::create([
            'product_code' => 'CANDIDATE-001',
            'product_type' => 'sake',
            'name' => 'Candidate product',
            'display_name' => 'Candidate product 720ml',
            'base_unit_id' => $bottle->id,
            'sales_unit_id' => $bottle->id,
            'inventory_unit_id' => $bottle->id,
            'capacity_value' => '720.0000',
            'capacity_unit_id' => $milliliter->id,
            'alcohol_percentage' => '15.00',
            'is_alcohol' => true,
            'is_inventory_managed' => true,
        ]);
        $lot = ProductionLot::create([
            'lot_code' => 'UNLINKED-LOT-001',
            'display_name' => 'Unlinked lot',
            'unit_id' => $bottle->id,
            'capacity_value' => '720.0000',
            'capacity_unit_id' => $milliliter->id,
            'alcohol_percentage' => '17.00',
            'analysis_status' => 'confirmed',
        ]);
        StockMovement::create([
            'status' => 'confirmed',
            'movement_type' => 'opening_stock',
            'movement_date' => '2026-07-01',
            'production_lot_id' => $lot->id,
            'stock_location_id' => $location->id,
            'unit_id' => $bottle->id,
            'quantity' => '12.0000',
            'confirmed_at' => now(),
        ]);

        $availability = app(ProductLotCandidateSummaryService::class)->forProduct($product, $location->id);

        $this->assertSame('0.0000', $availability['normal']);
        $this->assertSame('12.0000', $availability['approval_required']);
        $this->assertSame('12.0000', $availability['total']);
    }
}
