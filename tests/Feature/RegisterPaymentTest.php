<?php

namespace Tests\Feature;

use App\Exceptions\Billing\PaymentRegistrationException;
use App\Models\AppSetting;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\PaymentSchedule;
use App\Models\PriceList;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\SettlementReceivableCategory;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Services\Billing\ConfirmInvoiceService;
use App\Services\Billing\CreateInvoiceDraftData;
use App\Services\Billing\CreateInvoiceDraftService;
use App\Services\Billing\CreatePaymentScheduleService;
use App\Services\Billing\RegisterPaymentService;
use App\Services\Shipment\ApplyDraftShipmentPricingService;
use App\Services\Shipment\ConfirmShipmentService;
use App\Services\Shipment\CreateDraftShipmentData;
use App\Services\Shipment\CreateDraftShipmentLineData;
use App\Services\Shipment\CreateDraftShipmentService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\PriceMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_registers_full_payment_and_closes_schedule(): void
    {
        $schedule = $this->preparePaymentSchedule();

        $payment = app(RegisterPaymentService::class)->register(
            schedule: $schedule,
            amount: '3300.00',
            paymentDate: '2026-06-30',
            paymentMethod: 'bank_transfer',
            referenceNumber: 'BANK-001',
            reason: 'bank deposit confirmed',
        );

        $schedule->refresh();

        $this->assertSame($schedule->customer_id, $payment->customer_id);
        $this->assertSame('allocated', $payment->status);
        $this->assertSame('3300.00', $payment->amount);
        $this->assertSame('closed', $schedule->status);
        $this->assertSame('3300.00', $schedule->received_amount);
        $this->assertSame('0.00', $schedule->outstanding_amount);
        $this->assertNotNull($schedule->closed_at);
        $this->assertCount(1, $payment->allocations);
        $this->assertSame($schedule->id, $payment->allocations->first()->payment_schedule_id);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'payment.registered',
            'target_table' => 'payments',
            'target_id' => (string) $payment->id,
            'reason' => 'bank deposit confirmed',
        ]);
    }

    public function test_it_registers_partial_payment_and_keeps_schedule_open_for_balance(): void
    {
        $schedule = $this->preparePaymentSchedule();

        app(RegisterPaymentService::class)->register($schedule, '1000.00', '2026-06-20');

        $schedule->refresh();

        $this->assertSame('partial', $schedule->status);
        $this->assertSame('1000.00', $schedule->received_amount);
        $this->assertSame('2300.00', $schedule->outstanding_amount);
        $this->assertNull($schedule->closed_at);
    }

    public function test_it_records_excess_payment_as_unapplied_amount_requiring_review(): void
    {
        $schedule = $this->preparePaymentSchedule();

        $payment = app(RegisterPaymentService::class)->register($schedule, '3300.01', '2026-06-30');

        $schedule->refresh();

        $this->assertSame('review_required', $payment->status);
        $this->assertSame('3300.01', $payment->amount);
        $this->assertSame('0.01', $payment->unapplied_amount);
        $this->assertSame('closed', $schedule->status);
        $this->assertSame('3300.00', $schedule->received_amount);
        $this->assertSame('0.00', $schedule->outstanding_amount);
        $this->assertSame('3300.00', $payment->allocations->first()->allocated_amount);
    }

    public function test_it_rejects_payment_for_closed_schedule(): void
    {
        $schedule = $this->preparePaymentSchedule();
        app(RegisterPaymentService::class)->register($schedule, '3300.00', '2026-06-30');

        $this->expectException(PaymentRegistrationException::class);

        app(RegisterPaymentService::class)->register($schedule->refresh(), '1.00', '2026-07-01');
    }

    public function test_it_rejects_non_positive_payment_amount(): void
    {
        $schedule = $this->preparePaymentSchedule();

        $this->expectException(PaymentRegistrationException::class);

        app(RegisterPaymentService::class)->register($schedule, '0.00', '2026-06-30');
    }

    private function preparePaymentSchedule(): PaymentSchedule
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $shipment = app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: '2026-05-23',
            billingTargetDate: '2026-05-23',
            lines: [
                new CreateDraftShipmentLineData($product->id, '2.0000', $unit->id),
            ],
        ));
        $shipment = app(ApplyDraftShipmentPricingService::class)->apply($shipment);
        $shipment = app(ConfirmShipmentService::class)->confirm($shipment);

        $invoice = app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: '2026-05-31',
            dueDate: '2026-06-30',
            shipmentHeaderIds: [$shipment->id],
        ));
        $invoice = app(ConfirmInvoiceService::class)->confirm($invoice);

        return app(CreatePaymentScheduleService::class)->create($invoice);
    }

    /**
     * @return array{0: Customer, 1: Product, 2: Unit}
     */
    private function prepareBaseData(): array
    {
        $this->seed([
            CustomerMasterSeeder::class,
            ProductUnitMasterSeeder::class,
            PriceMasterSeeder::class,
            ShipmentMasterSeeder::class,
        ]);
        AppSetting::setValue('operational_start_date', '2026-01-01');

        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();
        $unit = Unit::where('code', 'bottle')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'PAY-REGISTER-CUST-001',
            'name' => 'Payment Register Customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'PAY-REGISTER-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Payment Register Sake',
            'display_name' => 'Payment Register Sake 720ml',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_alcohol' => true,
            'is_inventory_managed' => false,
        ]);

        PriceRule::create([
            'price_list_id' => PriceList::where('code', 'common')->firstOrFail()->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'unit_price' => '1500.0000',
            'priority' => 300,
            'effective_from' => '2026-01-01',
        ]);

        return [$customer, $product, $unit];
    }
}
