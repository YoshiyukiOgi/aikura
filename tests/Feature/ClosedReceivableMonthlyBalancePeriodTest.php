<?php

namespace Tests\Feature;

use App\Exceptions\Billing\ClosedReceivableMonthlyBalancePeriodException;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\InvoiceHeader;
use App\Models\PaymentSchedule;
use App\Models\PriceList;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\ReceivableMonthlyBalance;
use App\Models\SettlementReceivableCategory;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Services\Billing\CancelInvoiceService;
use App\Services\Billing\CancelPaymentService;
use App\Services\Billing\ConfirmInvoiceService;
use App\Services\Billing\ConfirmReceivableMonthlyBalanceService;
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

class ClosedReceivableMonthlyBalancePeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_invoice_confirmation_in_confirmed_receivable_month(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData('AR-CLOSED-INVOICE-CONFIRM');
        $invoice = $this->createDraftInvoice($customer, $product, $unit, '2026-06-20');
        $this->confirmReceivableMonth($customer);

        $this->expectException(ClosedReceivableMonthlyBalancePeriodException::class);

        app(ConfirmInvoiceService::class)->confirm($invoice);
    }

    public function test_it_rejects_confirmed_invoice_cancellation_in_confirmed_receivable_month(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData('AR-CLOSED-INVOICE-CANCEL');
        $invoice = app(ConfirmInvoiceService::class)->confirm(
            $this->createDraftInvoice($customer, $product, $unit, '2026-06-20'),
        );
        $this->confirmReceivableMonth($customer);

        $this->expectException(ClosedReceivableMonthlyBalancePeriodException::class);

        app(CancelInvoiceService::class)->cancel($invoice, 'cancel after receivable close');
    }

    public function test_it_rejects_payment_schedule_creation_in_confirmed_receivable_month(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData('AR-CLOSED-SCHEDULE-CREATE');
        $invoice = app(ConfirmInvoiceService::class)->confirm(
            $this->createDraftInvoice($customer, $product, $unit, '2026-06-20'),
        );
        $this->confirmReceivableMonth($customer);

        $this->expectException(ClosedReceivableMonthlyBalancePeriodException::class);

        app(CreatePaymentScheduleService::class)->create($invoice);
    }

    public function test_it_rejects_payment_registration_in_confirmed_receivable_month(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData('AR-CLOSED-PAYMENT-REGISTER');
        $schedule = $this->createPaymentSchedule($customer, $product, $unit, '2026-06-20');
        $this->confirmReceivableMonth($customer);

        $this->expectException(ClosedReceivableMonthlyBalancePeriodException::class);

        app(RegisterPaymentService::class)->register($schedule, '1000.00', '2026-06-25');
    }

    public function test_it_rejects_payment_cancellation_in_confirmed_receivable_month(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData('AR-CLOSED-PAYMENT-CANCEL');
        $schedule = $this->createPaymentSchedule($customer, $product, $unit, '2026-06-20');
        $payment = app(RegisterPaymentService::class)->register($schedule, '1000.00', '2026-06-25');
        $this->confirmReceivableMonth($customer);

        $this->expectException(ClosedReceivableMonthlyBalancePeriodException::class);

        app(CancelPaymentService::class)->cancel($payment, 'cancel after receivable close');
    }

    /**
     * @return array{0: Customer, 1: Product, 2: Unit}
     */
    private function prepareBaseData(string $codePrefix): array
    {
        $this->seed([
            CustomerMasterSeeder::class,
            ProductUnitMasterSeeder::class,
            PriceMasterSeeder::class,
            ShipmentMasterSeeder::class,
        ]);

        $unit = Unit::where('code', 'bottle')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => $codePrefix.'-CUST',
            'name' => $codePrefix.' Customer',
            'transaction_category_id' => TransactionCategory::where('code', 'wholesale')->firstOrFail()->id,
            'settlement_receivable_category_id' => SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail()->id,
            'billing_cycle_id' => BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail()->id,
        ]);

        $product = Product::create([
            'product_code' => $codePrefix.'-SAKE',
            'product_type' => 'sake',
            'name' => $codePrefix.' Sake',
            'display_name' => $codePrefix.' Sake 720ml',
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

    private function createPaymentSchedule(
        Customer $customer,
        Product $product,
        Unit $unit,
        string $invoiceDate,
    ): PaymentSchedule {
        $invoice = app(ConfirmInvoiceService::class)->confirm(
            $this->createDraftInvoice($customer, $product, $unit, $invoiceDate),
        );

        return app(CreatePaymentScheduleService::class)->create($invoice);
    }

    private function createDraftInvoice(
        Customer $customer,
        Product $product,
        Unit $unit,
        string $invoiceDate,
    ): InvoiceHeader {
        $shipment = app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: $invoiceDate,
            billingTargetDate: $invoiceDate,
            lines: [
                new CreateDraftShipmentLineData($product->id, '2.0000', $unit->id),
            ],
        ));
        $shipment = app(ApplyDraftShipmentPricingService::class)->apply($shipment);
        $shipment = app(ConfirmShipmentService::class)->confirm($shipment);

        return app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: $invoiceDate,
            dueDate: '2026-07-31',
            shipmentHeaderIds: [$shipment->id],
        ));
    }

    private function confirmReceivableMonth(Customer $customer): void
    {
        ReceivableMonthlyBalance::create([
            'status' => 'draft',
            'year' => 2026,
            'month' => 6,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'customer_id' => $customer->id,
            'customer_code' => $customer->customer_code,
            'customer_name' => $customer->name,
            'scheduled_amount' => '3300.00',
            'received_amount' => '0.00',
            'outstanding_amount' => '3300.00',
            'open_schedule_count' => 1,
            'partial_schedule_count' => 0,
            'closed_schedule_count' => 0,
            'calculated_at' => now(),
        ]);

        app(ConfirmReceivableMonthlyBalanceService::class)->confirm(2026, 6, 'monthly receivable close');
    }
}
