<?php

namespace Tests\Feature;

use App\Exceptions\Shipment\ShipmentPickException;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\SettlementReceivableCategory;
use App\Models\StockLocation;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Services\SalesOrder\CreateSalesOrderData;
use App\Services\SalesOrder\CreateSalesOrderLineData;
use App\Services\SalesOrder\CreateSalesOrderService;
use App\Services\Shipment\ApplyDraftShipmentPricingService;
use App\Services\Shipment\CancelShipmentService;
use App\Services\Shipment\ConfirmShipmentService;
use App\Services\Shipment\CreateDraftShipmentFromPickData;
use App\Services\Shipment\CreateDraftShipmentFromPickService;
use App\Services\ShipmentInstruction\CreateShipmentInstructionData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionLineData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionService;
use App\Services\ShipmentPicking\CancelShipmentPickService;
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

class PickOriginShipmentCancellationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelling_pick_origin_draft_shipment_does_not_restore_upstream_quantities(): void
    {
        [$salesOrder, $instruction, $pick, $shipment] = $this->preparePickOriginDraftShipment();

        $cancelledShipment = app(CancelShipmentService::class)->cancel($shipment, 'cancel pick origin draft');

        $this->assertSame('cancelled', $cancelledShipment->status);
        $this->assertSame('picked', $pick->refresh()->status);
        $this->assertSame('picked', $instruction->refresh()->status);
        $this->assertSame('instructed', $salesOrder->refresh()->status);
        $this->assertSame('4.0000', $instruction->lines->first()->refresh()->picked_quantity);
        $this->assertSame('0.0000', $salesOrder->lines->first()->refresh()->remaining_quantity);
        $this->assertSame($pick->id, $cancelledShipment->source_shipment_pick_id);

        $this->expectException(ShipmentPickException::class);

        app(CancelShipmentPickService::class)->cancel($pick, 'upstream cancellation remains blocked');
    }

    public function test_cancelling_pick_origin_confirmed_shipment_keeps_pick_and_instruction_as_history(): void
    {
        [$salesOrder, $instruction, $pick, $shipment] = $this->preparePickOriginDraftShipment();

        $shipment = app(ApplyDraftShipmentPricingService::class)->apply($shipment);
        $shipment = app(ConfirmShipmentService::class)->confirm($shipment);

        $cancelledShipment = app(CancelShipmentService::class)->cancel($shipment, 'cancel pick origin confirmed');

        $this->assertSame('cancelled', $cancelledShipment->status);
        $this->assertSame('picked', $pick->refresh()->status);
        $this->assertSame('picked', $instruction->refresh()->status);
        $this->assertSame('instructed', $salesOrder->refresh()->status);
        $this->assertSame('4.0000', $instruction->lines->first()->refresh()->picked_quantity);
        $this->assertSame('0.0000', $salesOrder->lines->first()->refresh()->remaining_quantity);
        $this->assertSame($pick->id, $cancelledShipment->source_shipment_pick_id);
        $this->assertSame($pick->lines->first()->id, $cancelledShipment->lines->first()->source_shipment_pick_line_id);
    }

    /**
     * @return array{0: \App\Models\SalesOrder, 1: \App\Models\ShipmentInstruction, 2: \App\Models\ShipmentPick, 3: \App\Models\ShipmentHeader}
     */
    private function preparePickOriginDraftShipment(): array
    {
        [$customer, $product, $unit, $location] = $this->prepareBaseData();

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

        $pick = app(PickShipmentInstructionService::class)->pick(new PickShipmentInstructionData(
            shipmentInstructionId: $instruction->id,
            pickDate: '2026-06-22',
            stockLocationId: $location->id,
            lines: [
                new PickShipmentInstructionLineData($instruction->lines->first()->id, '4.0000'),
            ],
        ));

        $shipment = app(CreateDraftShipmentFromPickService::class)->create(new CreateDraftShipmentFromPickData(
            shipmentPickId: $pick->id,
            documentDate: '2026-06-23',
            billingTargetDate: '2026-06-30',
        ));

        return [$salesOrder, $instruction, $pick, $shipment];
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
            'customer_code' => 'PICK-CANCEL-CUST-001',
            'name' => 'Pick Cancel Customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'PICK-CANCEL-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Pick Cancel Sake',
            'display_name' => 'Pick Cancel Sake 720ml',
            'base_unit_id' => $bottle->id,
            'sales_unit_id' => $bottle->id,
            'inventory_unit_id' => $bottle->id,
            'capacity_value' => '720.0000',
            'capacity_unit_id' => $milliliter->id,
            'alcohol_percentage' => '15.50',
            'is_alcohol' => true,
            'is_inventory_managed' => false,
        ]);

        PriceRule::create([
            'price_list_id' => PriceList::where('code', 'common')->firstOrFail()->id,
            'product_id' => $product->id,
            'unit_id' => $bottle->id,
            'unit_price' => '1500.0000',
            'priority' => 300,
            'effective_from' => '2026-01-01',
        ]);

        return [$customer, $product, $bottle, $location];
    }
}
