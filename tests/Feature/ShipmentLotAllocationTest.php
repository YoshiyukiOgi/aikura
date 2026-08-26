<?php

namespace Tests\Feature;

use App\Exceptions\Shipment\ShipmentConfirmationException;
use App\Exceptions\Shipment\ShipmentLotAllocationException;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\SettlementReceivableCategory;
use App\Models\ShipmentHeader;
use App\Models\ShipmentLine;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Services\Inventory\LotStockBalanceService;
use App\Services\Shipment\AllocateShipmentLineLotService;
use App\Services\Shipment\ApplyDraftShipmentPricingService;
use App\Services\Shipment\ConfirmShipmentService;
use App\Services\Shipment\CreateDraftShipmentData;
use App\Services\Shipment\CreateDraftShipmentLineData;
use App\Services\Shipment\CreateDraftShipmentService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\PriceMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Database\Seeders\StockLocationSeeder;
use Database\Seeders\TaxMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShipmentLotAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipment_lot_allocations_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('shipment_lot_allocations'));

        foreach ([
            'status',
            'shipment_header_id',
            'shipment_line_id',
            'product_id',
            'production_lot_id',
            'stock_location_id',
            'unit_id',
            'quantity',
            'allocated_at',
            'confirmed_at',
            'cancelled_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('shipment_lot_allocations', $column),
                "Column [shipment_lot_allocations.{$column}] does not exist.",
            );
        }
    }

    public function test_it_allocates_lot_stock_to_draft_shipment_line(): void
    {
        [$shipment, $line, $product, $unit, $location, $lot] = $this->prepareDraftWithLotStock('3.0000', '5.0000');

        $allocation = app(AllocateShipmentLineLotService::class)->allocate(
            shipmentLine: $line,
            productionLot: $lot,
            stockLocation: $location,
            quantity: '2.0000',
            reason: 'allocate test',
        );

        $this->assertSame('allocated', $allocation->status);
        $this->assertSame($shipment->id, $allocation->shipment_header_id);
        $this->assertSame($line->id, $allocation->shipment_line_id);
        $this->assertSame($product->id, $allocation->product_id);
        $this->assertSame($lot->id, $allocation->production_lot_id);
        $this->assertSame($location->id, $allocation->stock_location_id);
        $this->assertSame($unit->id, $allocation->unit_id);
        $this->assertSame('2.0000', $allocation->quantity);

        $balance = app(LotStockBalanceService::class)
            ->forLotProductLocationUnit($lot->id, $product->id, $location->id, $unit->id);

        $this->assertSame('5.0000', $balance->physicalQuantity);
        $this->assertSame('2.0000', $balance->allocatedQuantity);
        $this->assertSame('3.0000', $balance->availableQuantity);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'shipment_lot_allocation.allocated',
            'target_table' => 'shipment_lot_allocations',
            'target_id' => (string) $allocation->id,
            'reason' => 'allocate test',
        ]);
    }

    public function test_it_rejects_allocation_over_available_lot_stock(): void
    {
        [$shipment, $line, $product, $unit, $location, $lot] = $this->prepareDraftWithLotStock('3.0000', '1.0000');

        $this->expectException(ShipmentLotAllocationException::class);

        app(AllocateShipmentLineLotService::class)->allocate($line, $lot, $location, '2.0000');
    }

    public function test_it_rejects_allocation_over_shipment_line_quantity(): void
    {
        [$shipment, $line, $product, $unit, $location, $lot] = $this->prepareDraftWithLotStock('3.0000', '5.0000');

        app(AllocateShipmentLineLotService::class)->allocate($line, $lot, $location, '2.0000');

        $this->expectException(ShipmentLotAllocationException::class);

        app(AllocateShipmentLineLotService::class)->allocate($line, $lot, $location, '2.0000');
    }

    public function test_confirming_shipment_creates_lot_stock_movement_and_confirms_allocation(): void
    {
        [$shipment, $line, $product, $unit, $location, $lot] = $this->preparePricedDraftWithLotStock();

        $allocation = app(AllocateShipmentLineLotService::class)->allocate($line, $lot, $location, '3.0000');

        $confirmed = app(ConfirmShipmentService::class)->confirm($shipment);
        $allocation->refresh();

        $this->assertSame('confirmed', $allocation->status);
        $this->assertNotNull($allocation->confirmed_at);

        $this->assertDatabaseHas('stock_movements', [
            'status' => 'confirmed',
            'movement_type' => 'shipment',
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'quantity' => '-3.0000',
            'source_shipment_header_id' => $confirmed->id,
            'source_shipment_line_id' => $line->id,
            'production_lot_id' => $lot->id,
            'lot_code' => $lot->lot_code,
        ]);

        $balance = app(LotStockBalanceService::class)
            ->forLotProductLocationUnit($lot->id, $product->id, $location->id, $unit->id);

        $this->assertSame('2.0000', $balance->physicalQuantity);
        $this->assertSame('0.0000', $balance->allocatedQuantity);
        $this->assertSame('2.0000', $balance->availableQuantity);
    }

    public function test_confirmation_rejects_partial_lot_allocation(): void
    {
        [$shipment, $line, $product, $unit, $location, $lot] = $this->preparePricedDraftWithLotStock();

        app(AllocateShipmentLineLotService::class)->allocate($line, $lot, $location, '2.0000');

        $this->expectException(ShipmentConfirmationException::class);

        app(ConfirmShipmentService::class)->confirm($shipment);
    }

    /**
     * @return array{0: ShipmentHeader, 1: ShipmentLine, 2: Product, 3: Unit, 4: StockLocation, 5: ProductionLot}
     */
    private function prepareDraftWithLotStock(string $lineQuantity, string $stockQuantity): array
    {
        [$customer, $product, $unit, $location] = $this->prepareBaseData();

        $shipment = ShipmentHeader::create([
            'document_number' => 'ALLOC-TEST-001',
            'status' => 'draft',
            'customer_id' => $customer->id,
            'transaction_category_id' => $customer->transaction_category_id,
            'settlement_receivable_category_id' => $customer->settlement_receivable_category_id,
            'billing_cycle_id' => $customer->billing_cycle_id,
            'document_date' => '2026-06-20',
        ]);

        $line = ShipmentLine::create([
            'shipment_header_id' => $shipment->id,
            'line_no' => 1,
            'product_id' => $product->id,
            'quantity' => $lineQuantity,
            'unit_id' => $unit->id,
        ]);

        $lot = $this->createLot($product, $location);
        $this->createLotStock($product, $unit, $location, $lot, $stockQuantity);

        return [$shipment, $line, $product, $unit, $location, $lot];
    }

    /**
     * @return array{0: ShipmentHeader, 1: ShipmentLine, 2: Product, 3: Unit, 4: StockLocation, 5: ProductionLot}
     */
    private function preparePricedDraftWithLotStock(): array
    {
        [$customer, $product, $unit, $location] = $this->prepareBaseData();

        $priceList = PriceList::where('code', 'common')->firstOrFail();
        PriceRule::create([
            'price_list_id' => $priceList->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'unit_price' => '1500.0000',
            'priority' => 300,
            'effective_from' => '2026-01-01',
        ]);

        $shipment = app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: '2026-06-20',
            lines: [
                new CreateDraftShipmentLineData($product->id, '3.0000', $unit->id),
            ],
        ));
        $shipment = app(ApplyDraftShipmentPricingService::class)->apply($shipment);
        $line = $shipment->lines()->firstOrFail();

        $lot = $this->createLot($product, $location);
        $this->createLotStock($product, $unit, $location, $lot, '5.0000');

        return [$shipment, $line, $product, $unit, $location, $lot];
    }

    /**
     * @return array{0: Customer, 1: Product, 2: Unit, 3: StockLocation}
     */
    private function prepareBaseData(): array
    {
        $this->seed([
            CustomerMasterSeeder::class,
            ProductUnitMasterSeeder::class,
            PriceMasterSeeder::class,
            TaxMasterSeeder::class,
            ShipmentMasterSeeder::class,
            StockLocationSeeder::class,
        ]);

        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();
        $bottle = Unit::where('code', 'bottle')->firstOrFail();
        $milliliter = Unit::where('code', 'milliliter')->firstOrFail();
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'ALLOC-CUST-001',
            'name' => 'Allocation Customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'ALLOC-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Allocation Sake',
            'display_name' => 'Allocation Sake 720ml',
            'base_unit_id' => $bottle->id,
            'sales_unit_id' => $bottle->id,
            'inventory_unit_id' => $bottle->id,
            'capacity_value' => '720.0000',
            'capacity_unit_id' => $milliliter->id,
            'alcohol_percentage' => '15.50',
            'is_alcohol' => true,
            'is_inventory_managed' => true,
        ]);

        return [$customer, $product, $bottle, $location];
    }

    private function createLot(Product $product, StockLocation $location): ProductionLot
    {
        return ProductionLot::create([
            'lot_code' => 'ALLOC-LOT-001',
            'display_name' => 'Allocation Lot 001',
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'production_date' => '2026-06-01',
        ]);
    }

    private function createLotStock(
        Product $product,
        Unit $unit,
        StockLocation $location,
        ProductionLot $lot,
        string $quantity,
    ): void {
        StockMovement::create([
            'status' => 'confirmed',
            'movement_type' => 'inventory_adjustment',
            'movement_date' => '2026-06-10',
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'quantity' => $quantity,
            'production_lot_id' => $lot->id,
            'lot_code' => $lot->lot_code,
            'confirmed_at' => now(),
        ]);
    }
}
