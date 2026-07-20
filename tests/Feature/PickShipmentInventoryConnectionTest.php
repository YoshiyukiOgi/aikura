<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\SettlementReceivableCategory;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Models\User;
use App\Models\ApprovalRequest;
use App\Services\Approvals\ApprovalService;
use App\Services\Inventory\LotStockBalanceService;
use App\Services\SalesOrder\CreateSalesOrderData;
use App\Services\SalesOrder\CreateSalesOrderLineData;
use App\Services\SalesOrder\CreateSalesOrderService;
use App\Services\Shipment\AllocateShipmentLineLotService;
use App\Services\Shipment\ApplyDraftShipmentPricingService;
use App\Services\Shipment\ConfirmShipmentService;
use App\Services\Shipment\CreateDraftShipmentFromPickData;
use App\Services\Shipment\CreateDraftShipmentFromPickService;
use App\Services\Shipment\CreateDraftShipmentFromInstructionService;
use App\Services\Shipment\ReserveShipmentLineStockService;
use App\Services\ShipmentInstruction\CreateShipmentInstructionData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionLineData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionService;
use App\Services\ShipmentPicking\PickShipmentInstructionData;
use App\Services\ShipmentPicking\PickShipmentInstructionLineData;
use App\Services\ShipmentPicking\PickShipmentInstructionService;
use App\Services\ShipmentPicking\SavePickingLotAllocationsService;
use App\Services\Tax\AggregateMonthlyLiquorTaxTransfersService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\PriceMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Database\Seeders\StockLocationSeeder;
use Database\Seeders\TaxMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PickShipmentInventoryConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_pick_origin_shipment_can_allocate_and_confirm_lot_stock(): void
    {
        [$customer, $product, $unit, $location, $lot] = $this->prepareBaseData();
        $this->createLotStock($product, $unit, $location, $lot, '6.0000');

        $salesOrder = app(CreateSalesOrderService::class)->create(new CreateSalesOrderData(
            customerId: $customer->id,
            orderDate: '2026-06-20',
            requestedShipmentDate: '2026-06-23',
            billingTargetDate: '2026-06-30',
            lines: [
                new CreateSalesOrderLineData($product->id, '4.0000', $unit->id),
            ],
        ));

        $instruction = app(CreateShipmentInstructionService::class)->create(new CreateShipmentInstructionData(
            instructionDate: '2026-06-21',
            scheduledShipmentDate: '2026-06-23',
            stockLocationId: $location->id,
            lines: [
                new CreateShipmentInstructionLineData($salesOrder->lines->first()->id, '4.0000'),
            ],
        ));

        $pickingShipment = app(CreateDraftShipmentFromInstructionService::class)->create($instruction);
        $allocation = app(AllocateShipmentLineLotService::class)->allocate(
            shipmentLine: $pickingShipment->lines->firstOrFail(),
            productionLot: $lot,
            stockLocation: $location,
            quantity: '4.0000',
            reason: 'lot selected during picking',
        );

        $pick = app(PickShipmentInstructionService::class)->pick(new PickShipmentInstructionData(
            shipmentInstructionId: $instruction->id,
            pickDate: '2026-06-22',
            stockLocationId: $location->id,
            lines: [
                new PickShipmentInstructionLineData($instruction->lines->first()->id, '4.0000'),
            ],
        ));

        $draftShipment = app(CreateDraftShipmentFromPickService::class)->create(new CreateDraftShipmentFromPickData(
            shipmentPickId: $pick->id,
            documentDate: '2026-06-23',
            billingTargetDate: '2026-06-30',
        ));
        $line = $draftShipment->lines()->firstOrFail();

        $lotBeforeConfirm = app(LotStockBalanceService::class)
            ->forLotLocationUnit($lot->id, $location->id, $unit->id);

        $this->assertSame('6.0000', $lotBeforeConfirm->physicalQuantity);
        $this->assertSame('4.0000', $lotBeforeConfirm->allocatedQuantity);
        $this->assertSame('2.0000', $lotBeforeConfirm->availableQuantity);

        $pricedShipment = app(ApplyDraftShipmentPricingService::class)->apply($draftShipment);
        $confirmedShipment = app(ConfirmShipmentService::class)->confirm($pricedShipment);
        $confirmedLine = $confirmedShipment->lines()->firstOrFail();
        $allocation->refresh();

        $this->assertSame($pick->id, $confirmedShipment->source_shipment_pick_id);
        $this->assertSame($pick->lines->first()->id, $confirmedLine->source_shipment_pick_line_id);
        $this->assertSame('confirmed', $allocation->status);

        $this->assertDatabaseHas('stock_movements', [
            'status' => 'confirmed',
            'movement_type' => 'shipment',
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'quantity' => '-4.0000',
            'source_shipment_header_id' => $confirmedShipment->id,
            'source_shipment_line_id' => $confirmedLine->id,
            'production_lot_id' => $lot->id,
            'lot_code' => $lot->lot_code,
        ]);

        $lotAfterConfirm = app(LotStockBalanceService::class)
            ->forLotLocationUnit($lot->id, $location->id, $unit->id);

        $this->assertSame('2.0000', $lotAfterConfirm->physicalQuantity);
        $this->assertSame('0.0000', $lotAfterConfirm->allocatedQuantity);
        $this->assertSame('2.0000', $lotAfterConfirm->availableQuantity);
    }

    public function test_out_of_range_lot_is_split_and_requires_approval_before_pick(): void
    {
        [$customer, $product, $unit, $location, $normalLot] = $this->prepareBaseData();
        $normalLot->update(['alcohol_percentage' => '15.20', 'analysis_status' => 'confirmed']);
        $exceptionLot = ProductionLot::create([
            'lot_code' => 'PICK-INV-LOT-EXCEPTION', 'display_name' => 'Exception Lot',
            'stock_location_id' => $location->id, 'unit_id' => $unit->id, 'capacity_value' => '720.0000',
            'capacity_unit_id' => $product->capacity_unit_id, 'alcohol_percentage' => '17.00', 'analysis_status' => 'confirmed',
        ]);
        $this->createLotStock($product, $unit, $location, $normalLot, '10.0000');
        $this->createLotStock($product, $unit, $location, $exceptionLot, '10.0000');
        $requester = User::create(['name' => 'Picker', 'email' => 'picker@example.com', 'password' => bcrypt('password')]);
        $approver = User::create(['name' => 'Manager', 'email' => 'manager@example.com', 'password' => bcrypt('password')]);

        $salesOrder = app(CreateSalesOrderService::class)->create(new CreateSalesOrderData(
            customerId: $customer->id, orderDate: '2026-06-20', requestedShipmentDate: '2026-06-23', billingTargetDate: '2026-06-30',
            lines: [new CreateSalesOrderLineData($product->id, '4.0000', $unit->id)],
        ));
        $instruction = app(CreateShipmentInstructionService::class)->create(new CreateShipmentInstructionData(
            instructionDate: '2026-06-21', scheduledShipmentDate: '2026-06-23', stockLocationId: $location->id,
            lines: [new CreateShipmentInstructionLineData($salesOrder->lines->first()->id, '4.0000')],
        ));
        $draft = app(CreateDraftShipmentFromInstructionService::class)->create($instruction);
        $allocations = app(SavePickingLotAllocationsService::class)->save(
            $draft, $instruction->lines->first(), $location,
            collect([
                ['production_lot_id' => $normalLot->id, 'quantity' => '2.0000'],
                ['production_lot_id' => $exceptionLot->id, 'quantity' => '2.0000'],
            ]),
            'Use exception lot for this shipment', $requester,
        );

        $this->assertCount(2, $draft->lines()->get());
        $this->assertSame(['out_of_range', 'within_range'], $allocations->pluck('alcohol_compliance_status')->sort()->values()->all());
        $approval = ApprovalRequest::findOrFail($allocations->firstWhere('alcohol_compliance_status', 'out_of_range')->approval_request_id);
        $this->assertSame('pending', $approval->status);

        try {
            app(PickShipmentInstructionService::class)->pick(new PickShipmentInstructionData(
                shipmentInstructionId: $instruction->id, pickDate: '2026-06-22', stockLocationId: $location->id,
                lines: [new PickShipmentInstructionLineData($instruction->lines->first()->id, '4.0000')],
            ));
            $this->fail('Pending alcohol exception approval must block picking.');
        } catch (\App\Exceptions\Shipment\ShipmentPickException) {
            $this->assertTrue(true);
        }

        app(ApprovalService::class)->approve($approval, $approver, 'Approved exception lot');
        $pick = app(PickShipmentInstructionService::class)->pick(new PickShipmentInstructionData(
            shipmentInstructionId: $instruction->id, pickDate: '2026-06-22', stockLocationId: $location->id,
            lines: [new PickShipmentInstructionLineData($instruction->lines->first()->id, '4.0000')],
        ));
        $this->assertCount(2, $pick->lines);

        $shipment = app(CreateDraftShipmentFromPickService::class)->create(new CreateDraftShipmentFromPickData(shipmentPickId: $pick->id, documentDate: '2026-06-23', billingTargetDate: '2026-06-30'));
        $confirmed = app(ConfirmShipmentService::class)->confirm(app(ApplyDraftShipmentPricingService::class)->apply($shipment));
        $confirmedAllocations = $confirmed->lines()->with('lotAllocations')->get()->flatMap->lotAllocations;
        $this->assertSame(['15.20', '17.00'], $confirmedAllocations->pluck('actual_alcohol_percentage')->sort()->values()->all());
        $this->assertTrue($confirmedAllocations->every(fn ($allocation): bool => $allocation->liquor_taxable_kl !== null));
        $this->assertSame(2, StockMovement::query()->where('source_shipment_header_id', $confirmed->id)->where('movement_type', 'shipment')->count());

        $taxSummary = app(AggregateMonthlyLiquorTaxTransfersService::class)
            ->aggregate($confirmed->document_date->year, $confirmed->document_date->month)
            ->firstOrFail();
        $this->assertSame('0.002880', $taxSummary->taxableKl);
        $this->assertSame('288.00', $taxSummary->estimatedAmount);
    }

    /**
     * @return array{0: Customer, 1: Product, 2: Unit, 3: StockLocation, 4: ProductionLot}
     */
    private function prepareBaseData(): array
    {
        $this->seed([
            CustomerMasterSeeder::class,
            ProductUnitMasterSeeder::class,
            PriceMasterSeeder::class,
            ShipmentMasterSeeder::class,
            StockLocationSeeder::class,
            TaxMasterSeeder::class,
        ]);

        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();
        $bottle = Unit::where('code', 'bottle')->firstOrFail();
        $milliliter = Unit::where('code', 'milliliter')->firstOrFail();
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'PICK-INV-CUST-001',
            'name' => 'Pick Inventory Customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'PICK-INV-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Pick Inventory Sake',
            'display_name' => 'Pick Inventory Sake 720ml',
            'base_unit_id' => $bottle->id,
            'sales_unit_id' => $bottle->id,
            'inventory_unit_id' => $bottle->id,
            'capacity_value' => '720.0000',
            'capacity_unit_id' => $milliliter->id,
            'alcohol_percentage' => '15.50',
            'is_alcohol' => true,
            'is_inventory_managed' => true,
        ]);

        PriceRule::create([
            'price_list_id' => PriceList::where('code', 'common')->firstOrFail()->id,
            'product_id' => $product->id,
            'unit_id' => $bottle->id,
            'unit_price' => '1500.0000',
            'priority' => 300,
            'effective_from' => '2026-01-01',
        ]);

        $lot = ProductionLot::create([
            'lot_code' => 'PICK-INV-LOT-001',
            'display_name' => 'Pick Inventory Lot 001',
            'stock_location_id' => $location->id,
            'unit_id' => $bottle->id,
            'capacity_value' => '720.0000',
            'capacity_unit_id' => $milliliter->id,
            'alcohol_percentage' => '15.50',
            'analysis_status' => 'confirmed',
            'production_date' => '2026-06-01',
        ]);

        return [$customer, $product, $bottle, $location, $lot];
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
