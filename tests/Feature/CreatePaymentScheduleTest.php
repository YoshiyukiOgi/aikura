<?php

namespace Tests\Feature;

use App\Exceptions\Billing\PaymentScheduleException;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\InvoiceHeader;
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

class CreatePaymentScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_payment_schedule_from_confirmed_invoice(): void
    {
        $invoice = $this->prepareConfirmedInvoice(
            invoiceDate: '2026-07-31',
            dueDate: '2026-08-25',
        );

        $schedule = app(CreatePaymentScheduleService::class)->create(
            invoice: $invoice,
            reason: 'monthly receivable schedule',
        );

        $this->assertSame($invoice->id, $schedule->invoice_header_id);
        $this->assertSame($invoice->customer_id, $schedule->customer_id);
        $this->assertSame('open', $schedule->status);
        $this->assertSame('2026-08-25', $schedule->expected_payment_date->toDateString());
        $this->assertSame('3300.00', $schedule->scheduled_amount);
        $this->assertSame('0.00', $schedule->received_amount);
        $this->assertSame('3300.00', $schedule->outstanding_amount);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'payment_schedule.created',
            'target_table' => 'payment_schedules',
            'target_id' => (string) $schedule->id,
            'reason' => '請求書発行時に入金予定を自動作成',
        ]);
    }

    public function test_it_calculates_expected_payment_date_from_billing_cycle_when_due_date_is_empty(): void
    {
        $invoice = $this->prepareConfirmedInvoice(
            invoiceDate: '2026-07-31',
            dueDate: null,
        );

        $schedule = app(CreatePaymentScheduleService::class)->create($invoice);

        $this->assertSame('2026-08-31', $schedule->expected_payment_date->toDateString());
    }

    public function test_it_rejects_unconfirmed_invoice(): void
    {
        $invoice = $this->prepareInvoiceDraft();

        $this->expectException(PaymentScheduleException::class);

        app(CreatePaymentScheduleService::class)->create($invoice);
    }

    public function test_it_rejects_duplicate_schedule_for_same_invoice(): void
    {
        $invoice = $this->prepareConfirmedInvoice();

        $first = app(CreatePaymentScheduleService::class)->create($invoice);
        $second = app(CreatePaymentScheduleService::class)->create($invoice);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('open', $second->status);
    }

    private function prepareConfirmedInvoice(string $invoiceDate = '2026-07-31', ?string $dueDate = null): InvoiceHeader
    {
        return app(ConfirmInvoiceService::class)->confirm(
            $this->prepareInvoiceDraft($invoiceDate, $dueDate),
        );
    }

    private function prepareInvoiceDraft(string $invoiceDate = '2026-07-31', ?string $dueDate = null): InvoiceHeader
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $shipment = app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: '2026-07-23',
            billingTargetDate: '2026-07-23',
            lines: [
                new CreateDraftShipmentLineData($product->id, '2.0000', $unit->id),
            ],
        ));
        $shipment = app(ApplyDraftShipmentPricingService::class)->apply($shipment);
        $shipment = app(ConfirmShipmentService::class)->confirm($shipment);

        return app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: $invoiceDate,
            dueDate: $dueDate,
            shipmentHeaderIds: [$shipment->id],
        ));
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
        \App\Models\AppSetting::setValue('operational_start_date', '2026-01-01');

        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();
        $unit = Unit::where('code', 'bottle')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'PAY-SCHEDULE-CUST-001',
            'name' => 'Payment Schedule Customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'PAY-SCHEDULE-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Payment Schedule Sake',
            'display_name' => 'Payment Schedule Sake 720ml',
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
