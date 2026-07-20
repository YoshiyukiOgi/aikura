<?php

namespace Tests\Feature;

use App\Exceptions\SalesOrder\SalesOrderException;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SettlementReceivableCategory;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Services\SalesOrder\CancelSalesOrderService;
use App\Services\SalesOrder\CreateSalesOrderData;
use App\Services\SalesOrder\CreateSalesOrderLineData;
use App\Services\SalesOrder\CreateSalesOrderService;
use App\Services\ShipmentInstruction\CreateShipmentInstructionData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionLineData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Database\Seeders\StockLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelSalesOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_cancels_received_sales_order_with_reason_and_audit_log(): void
    {
        $salesOrder = $this->prepareSalesOrder('10.0000');

        $cancelled = app(CancelSalesOrderService::class)->cancel($salesOrder, 'customer cancelled');

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('customer cancelled', $cancelled->cancelled_reason);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame('10.0000', $cancelled->lines->first()->remaining_quantity);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'sales_order.cancelled',
            'target_table' => 'sales_orders',
            'target_id' => (string) $salesOrder->id,
            'reason' => 'customer cancelled',
        ]);
    }

    public function test_it_rejects_empty_reason(): void
    {
        $salesOrder = $this->prepareSalesOrder('10.0000');

        $this->expectException(SalesOrderException::class);

        app(CancelSalesOrderService::class)->cancel($salesOrder, '   ');
    }

    public function test_it_rejects_cancelling_already_cancelled_sales_order(): void
    {
        $salesOrder = $this->prepareSalesOrder('10.0000');
        $cancelled = app(CancelSalesOrderService::class)->cancel($salesOrder, 'first cancel');

        $this->expectException(SalesOrderException::class);

        app(CancelSalesOrderService::class)->cancel($cancelled, 'second cancel');
    }

    public function test_it_rejects_cancelling_instructed_sales_order(): void
    {
        $salesOrder = $this->prepareSalesOrder('10.0000');

        app(CreateShipmentInstructionService::class)->create(new CreateShipmentInstructionData(
            instructionDate: '2026-06-21',
            lines: [
                new CreateShipmentInstructionLineData($salesOrder->lines->first()->id, '4.0000'),
            ],
        ));

        $this->expectException(SalesOrderException::class);

        app(CancelSalesOrderService::class)->cancel($salesOrder, 'already instructed');
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
            'customer_code' => 'CANCEL-ORDER-CUST-001',
            'name' => 'Cancel order customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'CANCEL-ORDER-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Cancel order sake',
            'display_name' => 'Cancel order sake 720ml',
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
