<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\InvoiceHeader;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Models\SettlementReceivableCategory;
use App\Models\ShipmentHeader;
use App\Models\TransactionCategory;
use App\Services\Billing\CustomerMonthlyStatementService;
use App\Services\Billing\MonthlyBillingTargetService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyBillingTargetServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_includes_customer_with_carried_forward_balance_and_no_shipments(): void
    {
        $this->seed([CustomerMasterSeeder::class, ShipmentMasterSeeder::class]);

        $customer = Customer::create([
            'customer_code' => 'MONTHLY-TARGET-001',
            'name' => '繰越対象取引先',
            'transaction_category_id' => TransactionCategory::where('code', 'wholesale')->value('id'),
            'settlement_receivable_category_id' => SettlementReceivableCategory::where('code', 'accounts_receivable_1')->value('id'),
            'billing_cycle_id' => BillingCycle::where('code', 'monthly_end_next_month_end')->value('id'),
        ]);
        $priorInvoice = InvoiceHeader::create([
            'invoice_number' => 'I-TEST-TARGET-001',
            'status' => 'confirmed',
            'customer_id' => $customer->id,
            'billing_cycle_id' => $customer->billing_cycle_id,
            'invoice_date' => '2026-07-31',
            'billing_period_start' => '2026-07-01',
            'billing_period_end' => '2026-07-31',
            'total_amount' => '3300.00',
        ]);
        PaymentSchedule::create([
            'invoice_header_id' => $priorInvoice->id,
            'customer_id' => $customer->id,
            'status' => 'open',
            'expected_payment_date' => '2026-08-31',
            'scheduled_amount' => '3300.00',
            'received_amount' => '0.00',
            'outstanding_amount' => '3300.00',
        ]);

        $target = app(MonthlyBillingTargetService::class)
            ->forMonth(2026, 8)
            ->firstWhere('customer_id', $customer->id);

        $this->assertNotNull($target);
        $this->assertSame(0, $target['shipment_count']);
        $this->assertSame('3300.00', $target['previous_balance_amount']);
        $this->assertTrue($target['has_receivable_activity']);
    }

    public function test_monthly_statement_includes_legacy_imported_payment(): void
    {
        $this->seed(CustomerMasterSeeder::class);
        $customer = Customer::create([
            'customer_code' => 'MONTHLY-LEGACY-PAYMENT-001',
            'name' => '移行入金取引先',
            'transaction_category_id' => TransactionCategory::where('code', 'wholesale')->value('id'),
            'settlement_receivable_category_id' => SettlementReceivableCategory::where('code', 'accounts_receivable_1')->value('id'),
            'billing_cycle_id' => BillingCycle::where('code', 'monthly_end_next_month_end')->value('id'),
        ]);
        Payment::create([
            'customer_id' => $customer->id,
            'status' => 'legacy_imported',
            'payment_date' => '2026-08-20',
            'payment_method' => 'bank_transfer',
            'amount' => '1200.00',
            'unapplied_amount' => '0.00',
            'reference_number' => 'LEGACY-PAYMENT-001',
        ]);

        $row = app(CustomerMonthlyStatementService::class)
            ->forMonth(2026, 8)
            ->firstWhere('customerId', $customer->id);

        $this->assertNotNull($row);
        $this->assertSame('-1200.00', $row->paymentAmount);
    }

    public function test_it_excludes_no_shipment_customer_when_invoice_is_not_required(): void
    {
        $this->seed(CustomerMasterSeeder::class);
        $customer = Customer::create([
            'customer_code' => 'MONTHLY-NO-INVOICE-001',
            'name' => '請求不要取引先',
            'transaction_category_id' => TransactionCategory::where('code', 'wholesale')->value('id'),
            'settlement_receivable_category_id' => SettlementReceivableCategory::where('code', 'accounts_receivable_1')->value('id'),
            'billing_cycle_id' => BillingCycle::where('code', 'monthly_end_next_month_end')->value('id'),
            'invoice_required' => false,
        ]);
        $priorInvoice = InvoiceHeader::create([
            'invoice_number' => 'I-TEST-NO-INVOICE-001',
            'status' => 'confirmed',
            'customer_id' => $customer->id,
            'billing_cycle_id' => $customer->billing_cycle_id,
            'invoice_date' => '2026-07-31',
            'total_amount' => '3300.00',
        ]);
        PaymentSchedule::create([
            'invoice_header_id' => $priorInvoice->id,
            'customer_id' => $customer->id,
            'status' => 'open',
            'expected_payment_date' => '2026-08-31',
            'scheduled_amount' => '3300.00',
            'received_amount' => '0.00',
            'outstanding_amount' => '3300.00',
        ]);

        $target = app(MonthlyBillingTargetService::class)
            ->forMonth(2026, 8)
            ->firstWhere('customer_id', $customer->id);

        $this->assertNull($target);
    }

    public function test_it_excludes_self_consumption_shipments_from_monthly_billing(): void
    {
        $this->seed(CustomerMasterSeeder::class);
        $selfConsumption = SettlementReceivableCategory::where('code', 'self_consumption')->firstOrFail();
        $customer = Customer::create([
            'customer_code' => 'MONTHLY-SELF-CONSUMPTION-001',
            'name' => '自家用請求対象外',
            'transaction_category_id' => TransactionCategory::where('code', 'producer')->value('id'),
            'settlement_receivable_category_id' => $selfConsumption->id,
            'billing_cycle_id' => BillingCycle::where('code', 'monthly_end_next_month_end')->value('id'),
            'invoice_required' => false,
        ]);
        ShipmentHeader::create([
            'document_number' => 'S-SELF-CONSUMPTION-001',
            'status' => 'confirmed',
            'customer_id' => $customer->id,
            'transaction_category_id' => $customer->transaction_category_id,
            'settlement_receivable_category_id' => $selfConsumption->id,
            'billing_cycle_id' => $customer->billing_cycle_id,
            'document_date' => '2026-08-20',
            'actual_shipment_date' => '2026-08-20',
            'billing_target_date' => '2026-08-20',
            'confirmed_receivable_method' => 'none',
            'confirmed_invoice_required' => false,
        ]);

        $target = app(MonthlyBillingTargetService::class)
            ->forMonth(2026, 8)
            ->firstWhere('customer_id', $customer->id);

        $this->assertNull($target);
    }
}
