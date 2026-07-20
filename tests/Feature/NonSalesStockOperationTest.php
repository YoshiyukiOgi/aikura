<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\StockLocation;
use App\Models\Unit;
use App\Services\Inventory\CreateNonSalesStockOperationData;
use App\Services\Inventory\CreateNonSalesStockOperationLineData;
use App\Services\Inventory\CreateNonSalesStockOperationService;
use App\Services\Inventory\LotStockBalanceService;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NonSalesStockOperationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_non_sales_stock_operation_and_stock_movement(): void
    {
        [$product, $unit, $location] = $this->prepareBaseData();
        $lot = ProductionLot::create([
            'lot_code' => 'RETURN-LOT-001',
            'display_name' => 'Return lot 001',
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
        ]);

        $operation = app(CreateNonSalesStockOperationService::class)->create(new CreateNonSalesStockOperationData(
            operationType: 'disposal',
            operationDate: '2026-06-15',
            reason: 'broken bottle disposal',
            lines: [
                new CreateNonSalesStockOperationLineData(
                    productionLotId: $lot->id,
                    stockLocationId: $location->id,
                    quantity: '-1.0000',
                ),
            ],
        ));

        $line = $operation->lines->first();

        $this->assertStringStartsWith('NS-', $operation->operation_number);
        $this->assertSame('confirmed', $operation->status);
        $this->assertSame('disposal', $operation->operation_type);
        $this->assertNotNull($line->stock_movement_id);

        $this->assertDatabaseHas('stock_movements', [
            'id' => $line->stock_movement_id,
            'movement_type' => 'non_sales_disposal',
            'quantity' => '-1.0000',
            'source_document_number' => $operation->operation_number,
        ]);

        $balance = app(LotStockBalanceService::class)
            ->forLotLocationUnit($lot->id, $location->id, $unit->id);

        $this->assertSame('-1.0000', $balance->physicalQuantity);
    }

    /**
     * @return array{0: Product, 1: Unit, 2: StockLocation}
     */
    private function prepareBaseData(): array
    {
        $this->seed([
            ProductUnitMasterSeeder::class,
            ShipmentMasterSeeder::class,
        ]);

        $unit = Unit::where('code', 'bottle')->firstOrFail();
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();

        $product = Product::create([
            'product_code' => 'NS-STOCK-SAKE-001',
            'product_type' => 'sake',
            'name' => '販売外在庫確認酒',
            'display_name' => '販売外在庫確認酒 720ml',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_alcohol' => true,
            'is_inventory_managed' => true,
        ]);

        return [$product, $unit, $location];
    }
}
