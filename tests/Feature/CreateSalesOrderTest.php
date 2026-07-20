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
use App\Services\SalesOrder\CreateSalesOrderData;
use App\Services\SalesOrder\CreateSalesOrderLineData;
use App\Services\SalesOrder\CreateSalesOrderService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CreateSalesOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_order_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('sales_orders'));
        $this->assertTrue(Schema::hasTable('sales_order_lines'));

        foreach ([
            'order_number',
            'status',
            'customer_id',
            'transaction_category_id',
            'settlement_receivable_category_id',
            'billing_cycle_id',
            'order_date',
            'requested_shipment_date',
            'requested_delivery_date',
            'billing_target_date',
            'customer_order_number',
            'source_type',
            'source_reference',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('sales_orders', $column), "Column [sales_orders.{$column}] does not exist.");
        }

        foreach ([
            'sales_order_id',
            'line_no',
            'product_id',
            'quantity',
            'unit_id',
            'remaining_quantity',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('sales_order_lines', $column), "Column [sales_order_lines.{$column}] does not exist.");
        }
    }

    public function test_it_creates_sales_order_with_lines_and_audit_log(): void
    {
        [$customer, $product, $unit] = $this->prepareOrderData();

        $salesOrder = app(CreateSalesOrderService::class)->create(new CreateSalesOrderData(
            customerId: $customer->id,
            orderDate: '2026-06-20',
            requestedShipmentDate: '2026-06-22',
            requestedDeliveryDate: '2026-06-23',
            customerOrderNumber: 'PO-001',
            sourceType: 'phone',
            sourceReference: 'CALL-001',
            reason: 'order input',
            lines: [
                new CreateSalesOrderLineData(
                    productId: $product->id,
                    quantity: '12.0000',
                    unitId: $unit->id,
                    note: 'first line',
                ),
            ],
        ));

        $this->assertSame('received', $salesOrder->status);
        $this->assertStringStartsWith('O-', $salesOrder->order_number);
        $this->assertSame($customer->id, $salesOrder->customer_id);
        $this->assertSame($customer->transaction_category_id, $salesOrder->transaction_category_id);
        $this->assertSame($customer->settlement_receivable_category_id, $salesOrder->settlement_receivable_category_id);
        $this->assertSame($customer->billing_cycle_id, $salesOrder->billing_cycle_id);
        $this->assertSame('2026-06-20', $salesOrder->billing_target_date->toDateString());
        $this->assertSame('PO-001', $salesOrder->customer_order_number);
        $this->assertCount(1, $salesOrder->lines);
        $this->assertSame(1, $salesOrder->lines->first()->line_no);
        $this->assertSame('12.0000', $salesOrder->lines->first()->quantity);
        $this->assertSame('12.0000', $salesOrder->lines->first()->remaining_quantity);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'sales_order.created',
            'target_table' => 'sales_orders',
            'target_id' => (string) $salesOrder->id,
            'reason' => 'order input',
        ]);
    }

    public function test_it_allows_overriding_settlement_receivable_category_for_export_order(): void
    {
        [$customer, $product, $unit] = $this->prepareOrderData();
        $exportCategory = SettlementReceivableCategory::where('code', 'export')->firstOrFail();

        $salesOrder = app(CreateSalesOrderService::class)->create(new CreateSalesOrderData(
            customerId: $customer->id,
            orderDate: '2026-06-20',
            settlementReceivableCategoryId: $exportCategory->id,
            lines: [
                new CreateSalesOrderLineData($product->id, '3.0000', $unit->id),
            ],
        ));

        $this->assertSame($exportCategory->id, $salesOrder->settlement_receivable_category_id);
    }

    public function test_it_rejects_empty_lines(): void
    {
        [$customer] = $this->prepareOrderData();

        $this->expectException(SalesOrderException::class);

        app(CreateSalesOrderService::class)->create(new CreateSalesOrderData(
            customerId: $customer->id,
            orderDate: '2026-06-20',
            lines: [],
        ));
    }

    public function test_it_rejects_inactive_customer(): void
    {
        [$customer, $product, $unit] = $this->prepareOrderData();
        $customer->update(['is_active' => false, 'disabled_at' => now()]);

        $this->expectException(SalesOrderException::class);

        app(CreateSalesOrderService::class)->create(new CreateSalesOrderData(
            customerId: $customer->id,
            orderDate: '2026-06-20',
            lines: [
                new CreateSalesOrderLineData($product->id, '1.0000', $unit->id),
            ],
        ));
    }

    public function test_it_rejects_inactive_product(): void
    {
        [$customer, $product, $unit] = $this->prepareOrderData();
        $product->update(['is_sales_available' => false]);

        $this->expectException(SalesOrderException::class);

        app(CreateSalesOrderService::class)->create(new CreateSalesOrderData(
            customerId: $customer->id,
            orderDate: '2026-06-20',
            lines: [
                new CreateSalesOrderLineData($product->id, '1.0000', $unit->id),
            ],
        ));
    }

    public function test_it_rejects_non_positive_quantity(): void
    {
        [$customer, $product, $unit] = $this->prepareOrderData();

        $this->expectException(SalesOrderException::class);

        app(CreateSalesOrderService::class)->create(new CreateSalesOrderData(
            customerId: $customer->id,
            orderDate: '2026-06-20',
            lines: [
                new CreateSalesOrderLineData($product->id, '0.0000', $unit->id),
            ],
        ));
    }

    public function test_sales_order_number_sequence_increments(): void
    {
        [$customer, $product, $unit] = $this->prepareOrderData();
        $service = app(CreateSalesOrderService::class);

        $first = $service->create(new CreateSalesOrderData(
            customerId: $customer->id,
            orderDate: '2026-06-20',
            lines: [new CreateSalesOrderLineData($product->id, '1.0000', $unit->id)],
        ));

        $second = $service->create(new CreateSalesOrderData(
            customerId: $customer->id,
            orderDate: '2026-06-20',
            lines: [new CreateSalesOrderLineData($product->id, '2.0000', $unit->id)],
        ));

        $this->assertNotSame($first->order_number, $second->order_number);
        $this->assertSame(2, SalesOrder::count());
    }

    /**
     * @return array{0: Customer, 1: Product, 2: Unit}
     */
    private function prepareOrderData(): array
    {
        $this->seed([
            CustomerMasterSeeder::class,
            ProductUnitMasterSeeder::class,
            ShipmentMasterSeeder::class,
        ]);

        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();
        $unit = Unit::where('code', 'bottle')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'ORDER-CUST-001',
            'name' => 'Order Customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'ORDER-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Order Sake',
            'display_name' => 'Order Sake 720ml',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_alcohol' => true,
        ]);

        return [$customer, $product, $unit];
    }
}
