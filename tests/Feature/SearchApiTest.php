<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\AuditLog;
use App\Models\InvoiceHeader;
use App\Models\InvoiceLine;
use App\Models\OperationJob;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\SettlementReceivableCategory;
use App\Models\ShipmentHeader;
use App\Models\ShipmentLine;
use App\Models\ShipmentLotAllocation;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\FoundationPermissionSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Database\Seeders\StockLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SearchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_cross_search_finds_master_transaction_and_operation_records(): void
    {
        [$user] = $this->prepareData();

        $this->actingAs($user)
            ->getJson('/api/v1/search?q=SEARCH&limit=20')
            ->assertOk()
            ->assertJsonPath('data.search.query', 'SEARCH')
            ->assertJsonFragment(['type' => 'customer', 'label' => 'SEARCH-CUST-001'])
            ->assertJsonFragment(['type' => 'product', 'label' => 'SEARCH-SAKE-001'])
            ->assertJsonFragment(['type' => 'sales_order', 'label' => 'SEARCH-SO-001'])
            ->assertJsonFragment(['type' => 'shipment', 'label' => 'SEARCH-SH-001'])
            ->assertJsonFragment(['type' => 'invoice', 'label' => 'SEARCH-INV-001'])
            ->assertJsonFragment(['type' => 'payment', 'label' => 'SEARCH-PAY-001']);
    }

    public function test_transaction_search_filters_by_type_status_customer_product_and_date(): void
    {
        [$user, $customer, $product] = $this->prepareData();

        $this->actingAs($user)
            ->getJson('/api/v1/search/transactions?type=sales_order&status=received&customer_id='.$customer->id.'&product_id='.$product->id.'&date_from=2026-06-01&date_to=2026-06-30')
            ->assertOk()
            ->assertJsonPath('data.search.transactions.0.type', 'sales_order')
            ->assertJsonPath('data.search.transactions.0.label', 'SEARCH-SO-001')
            ->assertJsonPath('data.search.transactions.0.customer_id', $customer->id);
    }

    public function test_status_search_returns_counts_and_recent_items(): void
    {
        [$user] = $this->prepareData();

        $this->actingAs($user)
            ->getJson('/api/v1/search/statuses?type=shipment&status=confirmed')
            ->assertOk()
            ->assertJsonPath('data.search.statuses.shipments.counts.confirmed', 1)
            ->assertJsonPath('data.search.statuses.shipments.items.0.label', 'SEARCH-SH-001');
    }

    public function test_relation_search_returns_customer_product_and_lot_connections(): void
    {
        [$user, $customer, $product, $lot] = $this->prepareData();

        $this->actingAs($user)
            ->getJson('/api/v1/search/relations?customer_id='.$customer->id.'&product_id='.$product->id.'&production_lot_id='.$lot->id)
            ->assertOk()
            ->assertJsonPath('data.search.relations.customer.id', $customer->id)
            ->assertJsonPath('data.search.relations.product.id', $product->id)
            ->assertJsonPath('data.search.relations.production_lot.id', $lot->id)
            ->assertJsonPath('data.search.relations.sales_orders.0.label', 'SEARCH-SO-001')
            ->assertJsonPath('data.search.relations.shipments.0.label', 'SEARCH-SH-001')
            ->assertJsonPath('data.search.relations.stock_movements.0.production_lot_id', $lot->id);
    }

    public function test_pending_search_returns_open_operational_work(): void
    {
        [$user] = $this->prepareData();

        $this->actingAs($user)
            ->getJson('/api/v1/search/pending')
            ->assertOk()
            ->assertJsonPath('data.search.pending.sales_orders.0.label', 'SEARCH-SO-001')
            ->assertJsonPath('data.search.pending.invoices.0.label', 'SEARCH-INV-DRAFT-001')
            ->assertJsonPath('data.search.pending.payment_schedules.0.status', 'open');
    }

    public function test_review_required_search_returns_failed_jobs_and_cancelled_records(): void
    {
        [$user] = $this->prepareData();

        $this->actingAs($user)
            ->getJson('/api/v1/search/review-required')
            ->assertOk()
            ->assertJsonPath('data.search.review_required.failed_operation_jobs.0.label', 'SEARCH-FAILED-JOB')
            ->assertJsonPath('data.search.review_required.cancelled_shipments.0.label', 'SEARCH-SH-CANCELLED-001');
    }

    public function test_closing_target_search_returns_monthly_targets(): void
    {
        [$user] = $this->prepareData();

        $this->actingAs($user)
            ->getJson('/api/v1/search/closing-targets?year=2026&month=6')
            ->assertOk()
            ->assertJsonPath('data.search.period.start', '2026-06-01')
            ->assertJsonPath('data.search.closing_targets.stock_movements.0.label', 'SEARCH-SH-001')
            ->assertJsonPath('data.search.closing_targets.shipments_for_liquor_tax.0.label', 'SEARCH-SH-001')
            ->assertJsonPath('data.search.closing_targets.invoices_for_consumption_tax.0.label', 'SEARCH-INV-001');
    }

    public function test_audit_log_search_filters_operation_history(): void
    {
        [$user] = $this->prepareData();

        $this->actingAs($user)
            ->getJson('/api/v1/search/audit-logs?event=search.test&target_table=search_targets')
            ->assertOk()
            ->assertJsonPath('data.search.audit_logs.0.event', 'search.test')
            ->assertJsonPath('data.search.audit_logs.0.target_table', 'search_targets')
            ->assertJsonPath('data.search.audit_logs.0.reason', 'SEARCH audit reason');
    }

    public function test_user_without_search_permission_cannot_use_search_api(): void
    {
        $this->seed(FoundationPermissionSeeder::class);
        $user = $this->createUser('search-limited@example.com');

        $this->actingAs($user)
            ->getJson('/api/v1/search?q=SEARCH')
            ->assertForbidden()
            ->assertJsonPath('permission', 'search.view');
    }

    /**
     * @return array{0: User, 1: Customer, 2: Product, 3: ProductionLot}
     */
    private function prepareData(): array
    {
        $this->seed([
            FoundationPermissionSeeder::class,
            CustomerMasterSeeder::class,
            ProductUnitMasterSeeder::class,
            ShipmentMasterSeeder::class,
            StockLocationSeeder::class,
        ]);

        $user = $this->createUser('search-admin@example.com');
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail());

        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();
        $unit = Unit::where('code', 'bottle')->firstOrFail();
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'SEARCH-CUST-001',
            'name' => 'Search Customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
            'search_key' => 'SEARCH CUSTOMER',
        ]);

        $product = Product::create([
            'product_code' => 'SEARCH-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Search Sake',
            'display_name' => 'Search Sake 720ml',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_alcohol' => true,
            'is_inventory_managed' => true,
            'search_key' => 'SEARCH PRODUCT',
        ]);

        $lot = ProductionLot::create([
            'lot_code' => 'SEARCH-LOT-001',
            'display_name' => 'Search Lot 2026',
            'status' => 'available',
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'production_date' => '2026-05-20',
            'search_key' => 'SEARCH LOT',
        ]);

        $salesOrder = SalesOrder::create([
            'order_number' => 'SEARCH-SO-001',
            'status' => 'received',
            'customer_id' => $customer->id,
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
            'order_date' => '2026-06-01',
            'customer_order_number' => 'SEARCH-PO-001',
        ]);

        SalesOrderLine::create([
            'sales_order_id' => $salesOrder->id,
            'line_no' => 1,
            'product_id' => $product->id,
            'quantity' => '3.0000',
            'unit_id' => $unit->id,
            'remaining_quantity' => '3.0000',
        ]);

        $shipment = ShipmentHeader::create([
            'document_number' => 'SEARCH-SH-001',
            'status' => 'confirmed',
            'customer_id' => $customer->id,
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
            'document_date' => '2026-06-05',
            'actual_shipment_date' => '2026-06-05',
        ]);

        $shipmentLine = ShipmentLine::create([
            'shipment_header_id' => $shipment->id,
            'line_no' => 1,
            'product_id' => $product->id,
            'quantity' => '3.0000',
            'unit_id' => $unit->id,
        ]);

        ShipmentLotAllocation::create([
            'status' => 'confirmed',
            'shipment_header_id' => $shipment->id,
            'shipment_line_id' => $shipmentLine->id,
            'product_id' => $product->id,
            'production_lot_id' => $lot->id,
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'quantity' => '3.0000',
            'allocated_at' => now(),
            'confirmed_at' => now(),
        ]);

        StockMovement::create([
            'status' => 'confirmed',
            'movement_type' => 'shipment',
            'movement_date' => '2026-06-05',
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'quantity' => '-3.0000',
            'source_type' => 'shipment',
            'source_document_number' => 'SEARCH-SH-001',
            'source_line_no' => 1,
            'source_shipment_header_id' => $shipment->id,
            'source_shipment_line_id' => $shipmentLine->id,
            'production_lot_id' => $lot->id,
            'lot_code' => $lot->lot_code,
            'confirmed_at' => now(),
        ]);

        $invoice = InvoiceHeader::create([
            'invoice_number' => 'SEARCH-INV-001',
            'status' => 'confirmed',
            'customer_id' => $customer->id,
            'billing_cycle_id' => $billingCycle->id,
            'invoice_date' => '2026-06-30',
            'due_date' => '2026-07-31',
            'subtotal_amount' => '3000.00',
            'tax_amount' => '300.00',
            'total_amount' => '3300.00',
            'confirmed_at' => now(),
        ]);

        InvoiceLine::create([
            'invoice_header_id' => $invoice->id,
            'shipment_header_id' => $shipment->id,
            'shipment_line_id' => $shipmentLine->id,
            'line_no' => 1,
            'product_id' => $product->id,
            'product_code' => $product->product_code,
            'product_name' => $product->name,
            'display_name' => $product->display_name,
            'quantity' => '3.0000',
            'unit_code' => $unit->code,
            'unit_name' => $unit->name,
            'unit_price' => '1000.0000',
            'amount' => '3000.00',
            'tax_amount' => '300.00',
            'total_amount' => '3300.00',
        ]);

        PaymentSchedule::create([
            'invoice_header_id' => $invoice->id,
            'customer_id' => $customer->id,
            'status' => 'open',
            'expected_payment_date' => '2026-07-31',
            'scheduled_amount' => '3300.00',
            'received_amount' => '0.00',
            'outstanding_amount' => '3300.00',
        ]);

        Payment::create([
            'customer_id' => $customer->id,
            'status' => 'allocated',
            'payment_date' => '2026-07-31',
            'payment_method' => 'bank_transfer',
            'amount' => '3300.00',
            'reference_number' => 'SEARCH-PAY-001',
        ]);

        InvoiceHeader::create([
            'invoice_number' => 'SEARCH-INV-DRAFT-001',
            'status' => 'draft',
            'customer_id' => $customer->id,
            'billing_cycle_id' => $billingCycle->id,
            'invoice_date' => '2026-07-05',
            'due_date' => '2026-07-31',
            'subtotal_amount' => '0.00',
            'tax_amount' => '0.00',
            'total_amount' => '0.00',
        ]);

        ShipmentHeader::create([
            'document_number' => 'SEARCH-SH-CANCELLED-001',
            'status' => 'cancelled',
            'customer_id' => $customer->id,
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
            'document_date' => '2026-06-06',
            'cancelled_at' => now(),
            'cancelled_reason' => 'SEARCH cancelled reason',
        ]);

        OperationJob::create([
            'job_type' => 'SEARCH-FAILED-JOB',
            'status' => 'failed',
            'target_type' => 'search',
            'target_id' => 'SEARCH',
            'idempotency_key' => 'search-failed-job',
            'payload' => [],
            'attempts' => 1,
            'failed_at' => now(),
            'error_message' => 'SEARCH failure',
            'reason' => 'SEARCH failed reason',
        ]);

        AuditLog::create([
            'occurred_at' => now(),
            'user_id' => $user->id,
            'event' => 'search.test',
            'target_table' => 'search_targets',
            'target_id' => 'SEARCH-001',
            'reason' => 'SEARCH audit reason',
            'request_id' => (string) Str::uuid(),
        ]);

        return [$user, $customer, $product, $lot];
    }

    private function createUser(string $email): User
    {
        $employee = Employee::create([
            'employee_code' => 'SEARCHAPI'.str_pad((string) (Employee::count() + 1), 3, '0', STR_PAD_LEFT),
            'name' => 'Search API Employee',
            'email' => 'employee-'.$email,
        ]);

        return User::create([
            'employee_id' => $employee->id,
            'name' => 'Search API User',
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);
    }
}
