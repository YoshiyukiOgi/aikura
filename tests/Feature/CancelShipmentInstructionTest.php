<?php

namespace Tests\Feature;

use App\Exceptions\Shipment\ShipmentInstructionException;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SettlementReceivableCategory;
use App\Models\ShipmentInstruction;
use App\Models\StockLocation;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Services\SalesOrder\CreateSalesOrderData;
use App\Services\SalesOrder\CreateSalesOrderLineData;
use App\Services\SalesOrder\CreateSalesOrderService;
use App\Services\ShipmentInstruction\CancelShipmentInstructionService;
use App\Services\ShipmentInstruction\CreateShipmentInstructionData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionLineData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionService;
use App\Services\ShipmentPicking\PickShipmentInstructionData;
use App\Services\ShipmentPicking\PickShipmentInstructionLineData;
use App\Services\ShipmentPicking\PickShipmentInstructionService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Database\Seeders\StockLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelShipmentInstructionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_cancels_instruction_and_restores_sales_order_remaining_quantity(): void
    {
        [$salesOrder, $instruction] = $this->prepareInstruction('10.0000', '4.0000');
        $salesOrderLine = $salesOrder->lines->first();

        $cancelled = app(CancelShipmentInstructionService::class)->cancel($instruction, 'wrong instruction');

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('wrong instruction', $cancelled->cancelled_reason);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame('10.0000', $salesOrderLine->refresh()->remaining_quantity);
        $this->assertSame('received', $salesOrder->refresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'shipment_instruction.cancelled',
            'target_table' => 'shipment_instructions',
            'target_id' => (string) $instruction->id,
            'reason' => 'wrong instruction',
        ]);
    }

    public function test_it_recalculates_sales_order_status_when_other_instruction_remains(): void
    {
        [$salesOrder, $firstInstruction] = $this->prepareInstruction('10.0000', '4.0000');
        $salesOrderLine = $salesOrder->lines->first();

        app(CreateShipmentInstructionService::class)->create(new CreateShipmentInstructionData(
            instructionDate: '2026-06-21',
            lines: [
                new CreateShipmentInstructionLineData($salesOrderLine->id, '2.0000'),
            ],
        ));

        app(CancelShipmentInstructionService::class)->cancel($firstInstruction, 'partial cancel');

        $this->assertSame('8.0000', $salesOrderLine->refresh()->remaining_quantity);
        $this->assertSame('partially_instructed', $salesOrder->refresh()->status);
    }

    public function test_it_rejects_empty_reason(): void
    {
        [, $instruction] = $this->prepareInstruction('10.0000', '4.0000');

        $this->expectException(ShipmentInstructionException::class);

        app(CancelShipmentInstructionService::class)->cancel($instruction, '   ');
    }

    public function test_it_rejects_cancelling_already_cancelled_instruction(): void
    {
        [, $instruction] = $this->prepareInstruction('10.0000', '4.0000');
        $cancelled = app(CancelShipmentInstructionService::class)->cancel($instruction, 'first cancel');

        $this->expectException(ShipmentInstructionException::class);

        app(CancelShipmentInstructionService::class)->cancel($cancelled, 'second cancel');
    }

    public function test_it_rejects_cancelling_picked_instruction(): void
    {
        [, $instruction] = $this->prepareInstruction('10.0000', '4.0000');
        $instructionLine = $instruction->lines->first();

        app(PickShipmentInstructionService::class)->pick(new PickShipmentInstructionData(
            shipmentInstructionId: $instruction->id,
            pickDate: '2026-06-22',
            lines: [
                new PickShipmentInstructionLineData($instructionLine->id, '1.0000'),
            ],
        ));

        $this->expectException(ShipmentInstructionException::class);

        app(CancelShipmentInstructionService::class)->cancel($instruction, 'already picked');
    }

    /**
     * @return array{0: SalesOrder, 1: ShipmentInstruction}
     */
    private function prepareInstruction(string $orderQuantity, string $instructionQuantity): array
    {
        $salesOrder = $this->prepareSalesOrder($orderQuantity);
        $salesOrderLine = $salesOrder->lines->first();
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();

        $instruction = app(CreateShipmentInstructionService::class)->create(new CreateShipmentInstructionData(
            instructionDate: '2026-06-21',
            stockLocationId: $location->id,
            lines: [
                new CreateShipmentInstructionLineData($salesOrderLine->id, $instructionQuantity),
            ],
        ));

        return [$salesOrder, $instruction];
    }

    private function prepareSalesOrder(string $quantity): SalesOrder
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

        $customer = Customer::create([
            'customer_code' => 'CANCEL-INST-CUST-001',
            'name' => 'Cancel instruction customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'CANCEL-INST-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Cancel instruction sake',
            'display_name' => 'Cancel instruction sake 720ml',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_alcohol' => true,
        ]);

        return app(CreateSalesOrderService::class)->create(new CreateSalesOrderData(
            customerId: $customer->id,
            orderDate: '2026-06-20',
            lines: [
                new CreateSalesOrderLineData($product->id, $quantity, $unit->id),
            ],
        ));
    }
}
