<?php

namespace Tests\Feature;

use App\Exceptions\Billing\InvoiceDraftException;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\InvoiceHeader;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Models\PriceList;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\SettlementReceivableCategory;
use App\Models\ShipmentHeader;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Services\Billing\CancelInvoiceService;
use App\Services\Billing\ConfirmInvoiceService;
use App\Services\Billing\CreateInvoiceDraftData;
use App\Services\Billing\CreateInvoiceDraftService;
use App\Services\Shipment\ApplyDraftShipmentPricingService;
use App\Services\Shipment\CancelShipmentService;
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

class CreateInvoiceDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_invoice_draft_from_confirmed_shipments(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();
        $shipment = $this->createConfirmedShipment($customer, $product, $unit, '2026-07-10', '2.0000');

        $invoice = app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: '2026-07-31',
            billingPeriodStart: '2026-07-01',
            billingPeriodEnd: '2026-07-31',
            dueDate: '2026-08-31',
            reason: '5月分請求作成',
        ));

        $line = $invoice->lines->first();

        $this->assertSame('draft', $invoice->status);
        $this->assertStringStartsWith('I-', $invoice->invoice_number);
        $this->assertSame($customer->id, $invoice->customer_id);
        $this->assertSame('3000.00', $invoice->subtotal_amount);
        $this->assertSame('300.00', $invoice->tax_amount);
        $this->assertSame('3300.00', $invoice->total_amount);
        $this->assertCount(1, $invoice->lines);
        $this->assertSame($shipment->id, $line->shipment_header_id);
        $this->assertSame($shipment->lines->first()->id, $line->shipment_line_id);
        $this->assertSame($product->id, $line->product_id);
        $this->assertSame('請求ドラフト確認酒', $line->product_name);
        $this->assertSame('2.0000', $line->quantity);
        $this->assertSame('1500.0000', $line->unit_price);
        $this->assertSame('3000.00', $line->amount);
        $this->assertSame('taxable_standard', $line->consumption_tax_category_code);
        $this->assertSame('taxable', $line->consumption_taxability);
        $this->assertSame('0.1000', $line->tax_rate);
        $this->assertSame('300.00', $line->tax_amount);
        $this->assertSame('3300.00', $line->total_amount);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'invoice.draft_created',
            'target_table' => 'invoice_headers',
            'target_id' => (string) $invoice->id,
            'reason' => '5月分請求作成',
        ]);
    }

    public function test_invoice_line_snapshot_does_not_change_after_shipment_or_product_changes(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();
        $shipment = $this->createConfirmedShipment($customer, $product, $unit, '2026-07-10', '2.0000');

        $invoice = app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: '2026-07-31',
            shipmentHeaderIds: [$shipment->id],
        ));

        $product->update(['name' => '変更後商品名']);
        $shipment->lines->first()->update(['confirmed_product_name' => '変更後伝票名']);

        $line = $invoice->lines->first()->refresh();

        $this->assertSame('請求ドラフト確認酒', $line->product_name);
        $this->assertSame('請求ドラフト確認酒 表示名', $line->display_name);
    }

    public function test_it_excludes_cancelled_invoice_schedule_from_previous_balance(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();
        $firstShipment = $this->createConfirmedShipment($customer, $product, $unit, '2026-07-10', '2.0000');

        $firstInvoice = app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: '2026-07-31',
            shipmentHeaderIds: [$firstShipment->id],
        ));
        $firstInvoice = app(ConfirmInvoiceService::class)->confirm($firstInvoice);
        app(CancelInvoiceService::class)->cancel($firstInvoice, 'cancelled invoice must not be carried forward');

        $secondShipment = $this->createConfirmedShipment($customer, $product, $unit, '2026-08-10', '1.0000');
        $secondInvoice = app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: '2026-08-31',
            shipmentHeaderIds: [$secondShipment->id],
        ));

        $this->assertSame('0.00', $secondInvoice->previous_balance_amount);
        $this->assertSame('0.00', $secondInvoice->carried_forward_amount);
        $this->assertSame('1650.00', $secondInvoice->current_invoice_amount);
        $this->assertSame('1650.00', $secondInvoice->total_amount);
    }

    public function test_it_excludes_a_later_invoice_schedule_from_previous_balance_when_reissuing(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();
        $futureInvoice = InvoiceHeader::create([
            'invoice_number' => 'I-TEST-FUTURE-001',
            'status' => 'confirmed',
            'customer_id' => $customer->id,
            'billing_cycle_id' => $customer->billing_cycle_id,
            'invoice_date' => '2026-08-31',
            'total_amount' => '1650.00',
        ]);
        PaymentSchedule::create([
            'invoice_header_id' => $futureInvoice->id,
            'customer_id' => $customer->id,
            'status' => 'open',
            'expected_payment_date' => '2026-09-30',
            'scheduled_amount' => '1650.00',
            'received_amount' => '0.00',
            'outstanding_amount' => '1650.00',
        ]);

        $this->expectException(InvoiceDraftException::class);
        app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: '2026-07-31',
            billingPeriodStart: '2026-07-01',
            billingPeriodEnd: '2026-07-31',
        ));
    }

    public function test_confirming_invoice_preserves_carried_forward_in_total_amount(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();
        $firstShipment = $this->createConfirmedShipment($customer, $product, $unit, '2026-07-10', '2.0000');

        $firstInvoice = app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: '2026-07-31',
            shipmentHeaderIds: [$firstShipment->id],
        ));
        app(ConfirmInvoiceService::class)->confirm($firstInvoice);

        $secondShipment = $this->createConfirmedShipment($customer, $product, $unit, '2026-08-10', '1.0000');
        $secondInvoice = app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: '2026-08-31',
            shipmentHeaderIds: [$secondShipment->id],
        ));

        $confirmed = app(ConfirmInvoiceService::class)->confirm($secondInvoice);

        $this->assertSame('3300.00', $confirmed->previous_balance_amount);
        $this->assertSame('3300.00', $confirmed->carried_forward_amount);
        $this->assertSame('1650.00', $confirmed->current_invoice_amount);
        $this->assertSame('4950.00', $confirmed->total_amount);
        $this->assertDatabaseHas('payment_schedules', [
            'invoice_header_id' => $confirmed->id,
            'scheduled_amount' => '4950.00',
            'outstanding_amount' => '4950.00',
        ]);
    }

    public function test_period_payment_offsets_current_invoice_amount_after_previous_balance_is_cleared(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();
        $firstShipment = $this->createConfirmedShipment($customer, $product, $unit, '2026-07-10', '2.0000');

        $firstInvoice = app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: '2026-07-31',
            shipmentHeaderIds: [$firstShipment->id],
        ));
        app(ConfirmInvoiceService::class)->confirm($firstInvoice);

        Payment::create([
            'customer_id' => $customer->id,
            'status' => 'unallocated',
            'payment_date' => '2026-08-15',
            'payment_method' => 'bank_transfer',
            'amount' => '4950.00',
            'unapplied_amount' => '4950.00',
        ]);

        $secondShipment = $this->createConfirmedShipment($customer, $product, $unit, '2026-08-10', '1.0000');
        $secondInvoice = app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: '2026-08-31',
            billingPeriodStart: '2026-08-01',
            billingPeriodEnd: '2026-08-31',
        ));

        $confirmed = app(ConfirmInvoiceService::class)->confirm($secondInvoice);

        $this->assertSame('3300.00', $confirmed->previous_balance_amount);
        $this->assertSame('4950.00', $confirmed->period_payment_amount);
        $this->assertSame('0.00', $confirmed->carried_forward_amount);
        $this->assertSame('1650.00', $confirmed->current_invoice_amount);
        $this->assertSame('0.00', $confirmed->total_amount);
        $this->assertDatabaseHas('payment_schedules', [
            'invoice_header_id' => $confirmed->id,
            'scheduled_amount' => '0.00',
            'outstanding_amount' => '0.00',
        ]);
    }

    public function test_it_excludes_draft_and_cancelled_shipments(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();
        $draft = $this->createDraftShipment($customer, $product, $unit, '2026-07-10', '1.0000');
        $cancelled = app(CancelShipmentService::class)->cancel(
            $this->createConfirmedShipment($customer, $product, $unit, '2026-07-11', '1.0000'),
            '請求対象外',
        );

        $this->expectException(InvoiceDraftException::class);

        app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: '2026-07-31',
            shipmentHeaderIds: [$draft->id, $cancelled->id],
        ));
    }

    public function test_it_rejects_already_invoiced_shipment(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();
        $shipment = $this->createConfirmedShipment($customer, $product, $unit, '2026-07-10', '1.0000');

        app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: '2026-07-31',
            shipmentHeaderIds: [$shipment->id],
        ));

        $this->expectException(InvoiceDraftException::class);

        app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: '2026-07-31',
            shipmentHeaderIds: [$shipment->id],
        ));
    }

    public function test_it_rejects_when_no_billable_shipments_exist(): void
    {
        [$customer] = $this->prepareBaseData();

        $this->expectException(InvoiceDraftException::class);

        app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: '2026-07-31',
            billingPeriodStart: '2026-07-01',
            billingPeriodEnd: '2026-07-31',
        ));
    }

    public function test_it_creates_no_line_invoice_for_customer_with_carried_forward_balance(): void
    {
        [$customer] = $this->prepareBaseData();
        $firstInvoice = InvoiceHeader::create([
            'invoice_number' => 'I-TEST-PRIOR-001',
            'status' => 'confirmed',
            'customer_id' => $customer->id,
            'billing_cycle_id' => $customer->billing_cycle_id,
            'invoice_date' => '2026-07-31',
            'total_amount' => '3300.00',
        ]);
        PaymentSchedule::create([
            'invoice_header_id' => $firstInvoice->id,
            'customer_id' => $customer->id,
            'status' => 'open',
            'expected_payment_date' => '2026-08-31',
            'scheduled_amount' => '3300.00',
            'received_amount' => '0.00',
            'outstanding_amount' => '3300.00',
        ]);

        $invoice = app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: '2026-08-31',
            billingPeriodStart: '2026-08-01',
            billingPeriodEnd: '2026-08-31',
        ));

        $this->assertCount(0, $invoice->lines);
        $this->assertSame($firstInvoice->total_amount, $invoice->previous_balance_amount);
        $this->assertSame($firstInvoice->total_amount, $invoice->total_amount);
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
            'customer_code' => 'INV-DRAFT-CUST-001',
            'name' => '請求ドラフト確認酒店',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'INV-DRAFT-SAKE-001',
            'product_type' => 'sake',
            'name' => '請求ドラフト確認酒',
            'display_name' => '請求ドラフト確認酒 表示名',
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

    private function createDraftShipment(Customer $customer, Product $product, Unit $unit, string $billingTargetDate, string $quantity): ShipmentHeader
    {
        return app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: $billingTargetDate,
            billingTargetDate: $billingTargetDate,
            lines: [
                new CreateDraftShipmentLineData($product->id, $quantity, $unit->id),
            ],
        ));
    }

    private function createConfirmedShipment(Customer $customer, Product $product, Unit $unit, string $billingTargetDate, string $quantity): ShipmentHeader
    {
        $shipment = $this->createDraftShipment($customer, $product, $unit, $billingTargetDate, $quantity);
        $shipment = app(ApplyDraftShipmentPricingService::class)->apply($shipment);

        return app(ConfirmShipmentService::class)->confirm($shipment);
    }
}
