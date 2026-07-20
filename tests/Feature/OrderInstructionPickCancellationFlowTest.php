<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SettlementReceivableCategory;
use App\Models\ShipmentInstruction;
use App\Models\ShipmentPick;
use App\Models\StockLocation;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Services\SalesOrder\CancelSalesOrderService;
use App\Services\SalesOrder\CreateSalesOrderData;
use App\Services\SalesOrder\CreateSalesOrderLineData;
use App\Services\SalesOrder\CreateSalesOrderService;
use App\Services\ShipmentInstruction\CancelShipmentInstructionService;
use App\Services\ShipmentInstruction\CreateShipmentInstructionData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionLineData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionService;
use App\Services\ShipmentPicking\CancelShipmentPickService;
use App\Services\ShipmentPicking\PickShipmentInstructionData;
use App\Services\ShipmentPicking\PickShipmentInstructionLineData;
use App\Services\ShipmentPicking\PickShipmentInstructionService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Database\Seeders\StockLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderInstructionPickCancellationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_pick_instruction_and_order_can_be_cancelled_in_reverse_order(): void
    {
        [$salesOrder, $instruction, $pick] = $this->preparePickedOrderFlow('10.0000', '6.0000', '4.0000');

        app(CancelShipmentPickService::class)->cancel($pick, 'cancel pick before instruction');

        $salesOrderLine = $salesOrder->lines->first()->refresh();
        $instructionLine = $instruction->lines->first()->refresh();

        $this->assertSame('0.0000', $instructionLine->picked_quantity);
        $this->assertSame('instructed', $instruction->refresh()->status);
        $this->assertSame('4.0000', $salesOrderLine->remaining_quantity);
        $this->assertSame('partially_instructed', $salesOrder->refresh()->status);

        app(CancelShipmentInstructionService::class)->cancel($instruction, 'cancel instruction before order');

        $this->assertSame('10.0000', $salesOrderLine->refresh()->remaining_quantity);
        $this->assertSame('received', $salesOrder->refresh()->status);
        $this->assertSame('cancelled', $instruction->refresh()->status);

        app(CancelSalesOrderService::class)->cancel($salesOrder, 'customer cancelled order');

        $this->assertSame('cancelled', $salesOrder->refresh()->status);
        $this->assertSame('customer cancelled order', $salesOrder->cancelled_reason);
    }

    /**
     * @return array{0: SalesOrder, 1: ShipmentInstruction, 2: ShipmentPick}
     */
    private function preparePickedOrderFlow(string $orderQuantity, string $instructionQuantity, string $pickQuantity): array
    {
        [$customer, $product, $unit, $location] = $this->prepareBaseData();

        $salesOrder = app(CreateSalesOrderService::class)->create(new CreateSalesOrderData(
            customerId: $customer->id,
            orderDate: '2026-06-20',
            lines: [
                new CreateSalesOrderLineData($product->id, $orderQuantity, $unit->id),
            ],
        ));

        $instruction = app(CreateShipmentInstructionService::class)->create(new CreateShipmentInstructionData(
            instructionDate: '2026-06-21',
            stockLocationId: $location->id,
            lines: [
                new CreateShipmentInstructionLineData($salesOrder->lines->first()->id, $instructionQuantity),
            ],
        ));

        $pick = app(PickShipmentInstructionService::class)->pick(new PickShipmentInstructionData(
            shipmentInstructionId: $instruction->id,
            pickDate: '2026-06-22',
            stockLocationId: $location->id,
            lines: [
                new PickShipmentInstructionLineData($instruction->lines->first()->id, $pickQuantity),
            ],
        ));

        return [$salesOrder, $instruction, $pick];
    }

    /**
     * @return array{0: Customer, 1: Product, 2: Unit, 3: StockLocation}
     */
    private function prepareBaseData(): array
    {
        $this->seed([
            CustomerMasterSeeder::class,
            ProductUnitMasterSeeder::class,
            ShipmentMasterSeeder::class,
            StockLocationSeeder::class,
        ]);

        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();
        $unit = Unit::where('code', 'bottle')->firstOrFail();
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'CANCEL-FLOW-CUST-001',
            'name' => 'Cancel flow customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'CANCEL-FLOW-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Cancel flow sake',
            'display_name' => 'Cancel flow sake 720ml',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_alcohol' => true,
        ]);

        return [$customer, $product, $unit, $location];
    }
}
