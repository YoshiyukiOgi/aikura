<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Unit;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\StockLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_movements_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('stock_movements'));

        foreach ([
            'status',
            'movement_type',
            'movement_date',
            'stock_location_id',
            'unit_id',
            'quantity',
            'source_type',
            'source_document_number',
            'source_line_no',
            'source_shipment_header_id',
            'source_shipment_line_id',
            'related_stock_movement_id',
            'lot_code',
            'confirmed_at',
            'closed_at',
            'cancelled_at',
            'cancelled_reason',
            'reason',
            'note',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('stock_movements', $column),
                "Column [stock_movements.{$column}] does not exist.",
            );
        }
    }

    public function test_stock_movement_records_signed_quantity_for_location(): void
    {
        [$product, $unit, $location] = $this->prepareMasterData();

        StockMovement::create([
            'status' => 'confirmed',
            'movement_type' => 'production_receipt',
            'movement_date' => '2026-05-23',
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'quantity' => '12.0000',
            'source_type' => 'manual',
            'source_document_number' => 'STK-IN-001',
            'confirmed_at' => now(),
        ]);

        StockMovement::create([
            'status' => 'confirmed',
            'movement_type' => 'shipment',
            'movement_date' => '2026-05-24',
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'quantity' => '-3.0000',
            'source_type' => 'shipment',
            'source_document_number' => 'S-202605-000001',
            'source_line_no' => 1,
            'confirmed_at' => now(),
        ]);

        $quantity = StockMovement::query()
            ->where('stock_location_id', $location->id)
            ->where('unit_id', $unit->id)
            ->sum('quantity');

        $this->assertSame('9.0000', bcadd((string) $quantity, '0', 4));
    }

    public function test_stock_movement_relations_work(): void
    {
        [$product, $unit, $location] = $this->prepareMasterData();

        $movement = StockMovement::create([
            'movement_type' => 'inventory_adjustment',
            'movement_date' => '2026-05-23',
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'quantity' => '1.0000',
            'reason' => 'opening balance',
        ]);

        $this->assertTrue($movement->stockLocation->is($location));
        $this->assertTrue($movement->unit->is($unit));
        $this->assertSame('draft', $movement->refresh()->status);
    }

    public function test_related_stock_movement_can_represent_transfer_pair(): void
    {
        [$product, $unit, $fromLocation] = $this->prepareMasterData();

        $toLocation = StockLocation::where('code', 'cold_storage')->firstOrFail();

        $outbound = StockMovement::create([
            'status' => 'confirmed',
            'movement_type' => 'transfer',
            'movement_date' => '2026-05-23',
            'stock_location_id' => $fromLocation->id,
            'unit_id' => $unit->id,
            'quantity' => '-5.0000',
            'confirmed_at' => now(),
        ]);

        $inbound = StockMovement::create([
            'status' => 'confirmed',
            'movement_type' => 'transfer',
            'movement_date' => '2026-05-23',
            'stock_location_id' => $toLocation->id,
            'unit_id' => $unit->id,
            'quantity' => '5.0000',
            'related_stock_movement_id' => $outbound->id,
            'confirmed_at' => now(),
        ]);

        $this->assertTrue($inbound->relatedStockMovement->is($outbound));
        $this->assertTrue($outbound->relatedStockMovements->first()->is($inbound));
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
            'product_code' => 'test_sake',
            'product_type' => 'sake',
            'name' => 'Test Sake',
            'display_name' => 'Test Sake',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_alcohol' => true,
            'is_inventory_managed' => true,
        ]);

        return [$product, $unit, $location];
    }
}
