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
use App\Services\Billing\CreateInvoiceDraftData;
use App\Services\Billing\CreateInvoiceDraftService;
use App\Services\SalesOrder\CreateSalesOrderData;
use App\Services\SalesOrder\CreateSalesOrderLineData;
use App\Services\SalesOrder\CreateSalesOrderService;
use App\Services\Shipment\ApplyDraftShipmentPricingService;
use App\Services\Shipment\AllocateShipmentLineLotService;
use App\Services\Shipment\ConfirmShipmentService;
use App\Services\Shipment\CreateDraftShipmentFromInstructionService;
use App\Services\Shipment\CreateDraftShipmentFromPickData;
use App\Services\Shipment\CreateDraftShipmentFromPickService;
use App\Services\ShipmentInstruction\CreateShipmentInstructionData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionLineData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionService;
use App\Services\ShipmentPicking\PickShipmentInstructionData;
use App\Services\ShipmentPicking\PickShipmentInstructionLineData;
use App\Services\ShipmentPicking\PickShipmentInstructionService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\PriceMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Database\Seeders\StockLocationSeeder;
use Database\Seeders\TaxMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PickToInvoiceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_order_pick_shipment_and_invoice_flow(): void
    {
        [$customer, $product, $unit, $location, $lot] = $this->prepareBaseData();

        $salesOrder = app(CreateSalesOrderService::class)->create(new CreateSalesOrderData(
            customerId: $customer->id,
            orderDate: '2026-06-20',
            requestedShipmentDate: '2026-06-23',
            billingTargetDate: '2026-06-30',
            lines: [
                new CreateSalesOrderLineData($product->id, '5.0000', $unit->id),
            ],
        ));

        $instruction = app(CreateShipmentInstructionService::class)->create(new CreateShipmentInstructionData(
            instructionDate: '2026-06-21',
            scheduledShipmentDate: '2026-06-23',
            stockLocationId: $location->id,
            lines: [
                new CreateShipmentInstructionLineData($salesOrder->lines->first()->id, '5.0000'),
            ],
        ));
        $pickingShipment = app(CreateDraftShipmentFromInstructionService::class)->create($instruction);
        app(AllocateShipmentLineLotService::class)->allocate(
            $pickingShipment->lines()->firstOrFail(),
            $lot,
            $location,
            '5.0000',
            'test pick allocation',
        );

        $pick = app(PickShipmentInstructionService::class)->pick(new PickShipmentInstructionData(
            shipmentInstructionId: $instruction->id,
            pickDate: '2026-06-22',
            stockLocationId: $location->id,
            lines: [
                new PickShipmentInstructionLineData($instruction->lines->first()->id, '5.0000'),
            ],
        ));

        $draftShipment = app(CreateDraftShipmentFromPickService::class)->create(new CreateDraftShipmentFromPickData(
            shipmentPickId: $pick->id,
            documentDate: '2026-06-23',
            billingTargetDate: '2026-06-30',
        ));

        $pricedShipment = app(ApplyDraftShipmentPricingService::class)->apply($draftShipment);
        $confirmedShipment = app(ConfirmShipmentService::class)->confirm($pricedShipment);

        $invoice = app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: '2026-06-30',
            shipmentHeaderIds: [$confirmedShipment->id],
        ));

        $shipmentLine = $confirmedShipment->lines->first();
        $invoiceLine = $invoice->lines->first();

        $this->assertSame('instructed', $salesOrder->refresh()->status);
        $this->assertSame('0.0000', $salesOrder->lines->first()->refresh()->remaining_quantity);
        $this->assertSame('picked', $instruction->refresh()->status);
        $this->assertSame('5.0000', $instruction->lines->first()->refresh()->picked_quantity);
        $this->assertSame($pick->id, $confirmedShipment->source_shipment_pick_id);
        $this->assertSame($pick->lines->first()->id, $shipmentLine->source_shipment_pick_line_id);
        $this->assertSame('confirmed', $confirmedShipment->status);
        $this->assertSame('5.0000', $shipmentLine->confirmed_quantity);
        $this->assertSame('draft', $invoice->status);
        $this->assertSame($confirmedShipment->id, $invoiceLine->shipment_header_id);
        $this->assertSame($shipmentLine->id, $invoiceLine->shipment_line_id);
        $this->assertSame('7500.00', $invoice->subtotal_amount);
        $this->assertSame('8250.00', $invoice->total_amount);

        $this->assertSame(1, StockMovement::query()
            ->where('source_shipment_header_id', $confirmedShipment->id)
            ->where('source_shipment_line_id', $shipmentLine->id)
            ->where('quantity', '-5.0000')
            ->count());
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
        \App\Models\AppSetting::setValue('operational_start_date', '2026-01-01');

        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();
        $bottle = Unit::where('code', 'bottle')->firstOrFail();
        $milliliter = Unit::where('code', 'milliliter')->firstOrFail();
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'FLOW-CUST-001',
            'name' => 'Flow customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'FLOW-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Flow sake',
            'display_name' => 'Flow sake 720ml',
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
            'lot_code' => 'FLOW-LOT-001',
            'display_name' => 'Flow lot 001',
            'stock_location_id' => $location->id,
            'unit_id' => $bottle->id,
            'capacity_value' => '720.0000',
            'capacity_unit_id' => $milliliter->id,
            'alcohol_percentage' => '15.50',
            'analysis_status' => 'confirmed',
            'production_date' => '2026-06-01',
        ]);
        StockMovement::create([
            'status' => 'confirmed',
            'movement_type' => 'inventory_adjustment',
            'movement_date' => '2026-06-10',
            'stock_location_id' => $location->id,
            'unit_id' => $bottle->id,
            'quantity' => '5.0000',
            'production_lot_id' => $lot->id,
            'lot_code' => $lot->lot_code,
            'confirmed_at' => now(),
        ]);

        return [$customer, $product, $bottle, $location, $lot];
    }
}
