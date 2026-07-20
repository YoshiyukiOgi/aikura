<?php

namespace Tests\Feature;

use App\Exceptions\Billing\PaymentCancellationException;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\PaymentSchedule;
use App\Models\PriceList;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\SettlementReceivableCategory;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Services\Billing\CancelPaymentService;
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

class CancelPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_cancels_full_payment_and_reopens_schedule(): void
    {
        $schedule = $this->preparePaymentSchedule();
        $payment = app(RegisterPaymentService::class)->register($schedule, '3300.00', '2026-06-30');

        $cancelled = app(CancelPaymentService::class)->cancel($payment, 'wrong deposit');

        $schedule->refresh();

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame('wrong deposit', $cancelled->cancelled_reason);
        $this->assertSame('open', $schedule->status);
        $this->assertSame('0.00', $schedule->received_amount);
        $this->assertSame('3300.00', $schedule->outstanding_amount);
        $this->assertNull($schedule->closed_at);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'payment.cancelled',
            'target_table' => 'payments',
            'target_id' => (string) $payment->id,
            'reason' => 'wrong deposit',
        ]);
    }

    public function test_it_cancels_one_partial_payment_and_restores_remaining_balance(): void
    {
        $schedule = $this->preparePaymentSchedule();
        $firstPayment = app(RegisterPaymentService::class)->register($schedule, '1000.00', '2026-06-20');
        app(RegisterPaymentService::class)->register($schedule->refresh(), '500.00', '2026-06-25');

        app(CancelPaymentService::class)->cancel($firstPayment, 'partial payment reversal');

        $schedule->refresh();

        $this->assertSame('partial', $schedule->status);
        $this->assertSame('500.00', $schedule->received_amount);
        $this->assertSame('2800.00', $schedule->outstanding_amount);
        $this->assertNull($schedule->closed_at);
    }

    public function test_it_rejects_empty_cancellation_reason(): void
    {
        $schedule = $this->preparePaymentSchedule();
        $payment = app(RegisterPaymentService::class)->register($schedule, '1000.00', '2026-06-20');

        $this->expectException(PaymentCancellationException::class);

        app(CancelPaymentService::class)->cancel($payment, '   ');
    }

    public function test_it_rejects_cancelling_payment_twice(): void
    {
        $schedule = $this->preparePaymentSchedule();
        $payment = app(RegisterPaymentService::class)->register($schedule, '1000.00', '2026-06-20');
        $payment = app(CancelPaymentService::class)->cancel($payment, 'first cancellation');

        $this->expectException(PaymentCancellationException::class);

        app(CancelPaymentService::class)->cancel($payment, 'second cancellation');
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

        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();
        $unit = Unit::where('code', 'bottle')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'PAY-CANCEL-CUST-001',
            'name' => 'Payment Cancel Customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'PAY-CANCEL-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Payment Cancel Sake',
            'display_name' => 'Payment Cancel Sake 720ml',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_alcohol' => true,
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
