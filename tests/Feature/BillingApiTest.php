<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\InvoiceHeader;
use App\Models\Payment;
use App\Models\PriceList;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\Role;
use App\Models\SettlementReceivableCategory;
use App\Models\ShipmentHeader;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Models\User;
use App\Services\Shipment\ApplyDraftShipmentPricingService;
use App\Services\Shipment\ConfirmShipmentService;
use App\Services\Shipment\CreateDraftShipmentData;
use App\Services\Shipment\CreateDraftShipmentLineData;
use App\Services\Shipment\CreateDraftShipmentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_permission_can_create_confirm_schedule_pay_cancel_and_view_receivable(): void
    {
        [$user, $customer, $shipment] = $this->prepareData();

        $createInvoice = $this->actingAs($user)
            ->postJson('/api/v1/billing/invoices', [
                'customer_id' => $customer->id,
                'invoice_date' => '2026-07-31',
                'due_date' => '2026-08-31',
                'shipment_header_ids' => [$shipment->id],
                'reason' => 'api invoice draft',
            ])
            ->assertCreated()
            ->assertJsonPath('data.invoice.status', 'draft')
            ->assertJsonPath('data.invoice.total_amount', '3300.00');

        $invoiceId = $createInvoice->json('data.invoice.id');

        $this->actingAs($user)
            ->postJson("/api/v1/billing/invoices/{$invoiceId}/confirm", [
                'reason' => 'api invoice confirm',
            ])
            ->assertOk()
            ->assertJsonPath('data.invoice.status', 'confirmed');

        $createSchedule = $this->actingAs($user)
            ->postJson('/api/v1/billing/payment-schedules', [
                'invoice_header_id' => $invoiceId,
                'reason' => 'api payment schedule',
            ])
            ->assertCreated()
            ->assertJsonPath('data.payment_schedule.status', 'open')
            ->assertJsonPath('data.payment_schedule.outstanding_amount', '3300.00');

        $scheduleId = $createSchedule->json('data.payment_schedule.id');

        $createPayment = $this->actingAs($user)
            ->postJson('/api/v1/billing/payments', [
                'payment_schedule_id' => $scheduleId,
                'amount' => '1000.00',
                'payment_date' => '2026-07-20',
                'payment_method' => 'bank_transfer',
                'reference_number' => 'API-PAY-001',
                'reason' => 'api payment register',
            ])
            ->assertCreated()
            ->assertJsonPath('data.payment.status', 'allocated')
            ->assertJsonPath('data.payment.amount', '1000.00');

        $paymentId = $createPayment->json('data.payment.id');

        $this->actingAs($user)
            ->getJson("/api/v1/billing/receivables?customer_id={$customer->id}")
            ->assertOk()
            ->assertJsonPath('data.receivable_balance.scheduled_amount', '3300.00')
            ->assertJsonPath('data.receivable_balance.received_amount', '1000.00')
            ->assertJsonPath('data.receivable_balance.outstanding_amount', '2300.00');

        $this->actingAs($user)
            ->postJson("/api/v1/billing/payments/{$paymentId}/cancel", [
                'reason' => 'wrong payment by api',
            ])
            ->assertOk()
            ->assertJsonPath('data.payment.status', 'cancelled')
            ->assertJsonPath('data.payment.cancelled_reason', 'wrong payment by api');

        $this->actingAs($user)
            ->getJson("/api/v1/billing/invoices/{$invoiceId}")
            ->assertOk()
            ->assertJsonPath('data.invoice.id', $invoiceId)
            ->assertJsonPath('data.invoice.status', 'confirmed');
    }

    public function test_user_without_billing_invoice_create_permission_cannot_create_invoice(): void
    {
        [, $customer, $shipment] = $this->prepareData();
        $user = $this->createUser('limited-billing@example.com');

        $this->actingAs($user)
            ->postJson('/api/v1/billing/invoices', [
                'customer_id' => $customer->id,
                'invoice_date' => '2026-07-31',
                'shipment_header_ids' => [$shipment->id],
            ])
            ->assertForbidden()
            ->assertJsonPath('permission', 'billing.invoice.create');
    }

    public function test_invoice_create_api_validates_required_invoice_date(): void
    {
        [$user, $customer, $shipment] = $this->prepareData();

        $this->actingAs($user)
            ->postJson('/api/v1/billing/invoices', [
                'customer_id' => $customer->id,
                'shipment_header_ids' => [$shipment->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['invoice_date']);
    }

    public function test_legacy_period_invoices_are_hidden_and_not_operable(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->createUser('billing-legacy-admin@example.com');
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail());

        $customer = Customer::create([
            'customer_code' => 'API-BILL-LEGACY-CUST',
            'name' => 'API Billing Legacy Customer',
            'transaction_category_id' => TransactionCategory::where('code', 'wholesale')->firstOrFail()->id,
            'settlement_receivable_category_id' => SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail()->id,
            'billing_cycle_id' => BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail()->id,
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/billing/invoices', [
                'customer_id' => $customer->id,
                'invoice_date' => '2026-05-31',
                'reason' => 'legacy period invoice',
            ])
            ->assertUnprocessable();

        $legacyInvoice = InvoiceHeader::query()->create([
            'invoice_number' => 'LEGACY-INV-202605',
            'status' => 'confirmed',
            'document_type' => 'invoice',
            'customer_id' => $customer->id,
            'billing_cycle_id' => $customer->billing_cycle_id,
            'invoice_date' => '2026-05-31',
            'billing_period_start' => '2026-05-01',
            'billing_period_end' => '2026-05-31',
            'subtotal_amount' => '1000.00',
            'tax_amount' => '100.00',
            'total_amount' => '1100.00',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/billing/invoices')
            ->assertOk()
            ->assertJsonMissing(['id' => $legacyInvoice->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/billing/invoices/{$legacyInvoice->id}")
            ->assertNotFound();
    }

    public function test_payment_list_shows_only_imported_history_before_operational_start(): void
    {
        [$user, $customer] = $this->prepareData();

        $imported = Payment::query()->create([
            'customer_id' => $customer->id,
            'is_legacy_history' => true,
            'status' => 'legacy_imported',
            'payment_date' => '2026-06-20',
            'payment_method' => 'bank_transfer',
            'amount' => '1000.00',
            'unapplied_amount' => '0.00',
            'reference_number' => 'ITARO-PAY-TEST-1',
        ]);
        $hidden = Payment::query()->create([
            'customer_id' => $customer->id,
            'is_legacy_history' => false,
            'status' => 'review_required',
            'payment_date' => '2026-06-20',
            'payment_method' => 'bank_transfer',
            'amount' => '2000.00',
            'unapplied_amount' => '2000.00',
            'reference_number' => 'NON-IMPORTED-PAYMENT',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/billing/payments?payment_date_from=2026-06-01&payment_date_to=2026-06-30')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.payments.0.id', $imported->id)
            ->assertJsonMissing(['id' => $hidden->id]);
    }

    /**
     * @return array{0: User, 1: Customer, 2: ShipmentHeader}
     */
    private function prepareData(): array
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->createUser('billing-admin@example.com');
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail());

        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();
        $bottle = Unit::where('code', 'bottle')->firstOrFail();
        $milliliter = Unit::where('code', 'milliliter')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'API-BILL-CUST-001',
            'name' => 'API Billing Customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'API-BILL-SAKE-001',
            'product_type' => 'sake',
            'name' => 'API Billing Sake',
            'display_name' => 'API Billing Sake 720ml',
            'base_unit_id' => $bottle->id,
            'sales_unit_id' => $bottle->id,
            'inventory_unit_id' => $bottle->id,
            'capacity_value' => '720.0000',
            'capacity_unit_id' => $milliliter->id,
            'alcohol_percentage' => '15.50',
            'is_alcohol' => true,
            'is_sales_available' => true,
            'is_active' => true,
        ]);

        PriceRule::create([
            'price_list_id' => PriceList::where('code', 'common')->firstOrFail()->id,
            'product_id' => $product->id,
            'unit_id' => $bottle->id,
            'unit_price' => '1500.0000',
            'priority' => 300,
            'effective_from' => '2026-01-01',
        ]);

        $shipment = app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: '2026-07-20',
            billingTargetDate: '2026-07-20',
            lines: [
                new CreateDraftShipmentLineData($product->id, '2.0000', $bottle->id),
            ],
        ));
        $shipment = app(ApplyDraftShipmentPricingService::class)->apply($shipment);
        $shipment = app(ConfirmShipmentService::class)->confirm($shipment);

        return [$user, $customer, $shipment];
    }

    private function createUser(string $email): User
    {
        $employee = Employee::create([
            'employee_code' => 'BILLAPI'.str_pad((string) (Employee::count() + 1), 3, '0', STR_PAD_LEFT),
            'name' => 'Billing API Employee',
            'email' => 'employee-'.$email,
        ]);

        return User::create([
            'employee_id' => $employee->id,
            'name' => 'Billing API User',
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);
    }
}
