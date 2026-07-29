<?php

namespace Tests\Feature;

use App\Exceptions\Billing\InvoiceConfirmationException;
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

class ConfirmInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_confirms_invoice_draft_and_records_audit_log(): void
    {
        $invoice = $this->prepareInvoiceDraft(quantity: '2.0000', unitPrice: '1500.0000');

        $confirmed = app(ConfirmInvoiceService::class)->confirm(
            invoice: $invoice,
            reason: '請求内容確認済み',
        );

        $this->assertSame('confirmed', $confirmed->status);
        $this->assertSame('3000.00', $confirmed->subtotal_amount);
        $this->assertSame('300.00', $confirmed->tax_amount);
        $this->assertSame('3300.00', $confirmed->total_amount);
        $this->assertNotNull($confirmed->confirmed_at);
        $this->assertDatabaseHas('payment_schedules', [
            'invoice_header_id' => $confirmed->id,
            'customer_id' => $confirmed->customer_id,
            'status' => 'open',
            'scheduled_amount' => '3300.00',
            'outstanding_amount' => '3300.00',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'invoice.confirmed',
            'target_table' => 'invoice_headers',
            'target_id' => (string) $confirmed->id,
            'reason' => '請求内容確認済み',
        ]);
    }

    public function test_it_rejects_confirming_non_draft_invoice(): void
    {
        $invoice = $this->prepareInvoiceDraft();
        $invoice = app(ConfirmInvoiceService::class)->confirm($invoice);

        $this->expectException(InvoiceConfirmationException::class);

        app(ConfirmInvoiceService::class)->confirm($invoice);
    }

    public function test_it_rejects_invoice_without_lines(): void
    {
        [$customer] = $this->prepareBaseData();

        $invoice = InvoiceHeader::create([
            'status' => 'draft',
            'customer_id' => $customer->id,
            'billing_cycle_id' => $customer->billing_cycle_id,
            'invoice_date' => '2026-05-31',
        ]);

        $this->expectException(InvoiceConfirmationException::class);

        app(ConfirmInvoiceService::class)->confirm($invoice);
    }

    public function test_confirmed_invoice_line_snapshot_does_not_change_after_product_or_shipment_changes(): void
    {
        [$invoice, $product, $shipment] = $this->prepareInvoiceDraftWithSources();

        $confirmed = app(ConfirmInvoiceService::class)->confirm($invoice);
        $invoiceLine = $confirmed->lines->first();

        $product->update(['name' => '請求確定後の商品名変更']);
        $shipment->lines->first()->update(['confirmed_product_name' => '請求確定後の出荷名変更']);

        $invoiceLine->refresh();

        $this->assertSame('請求確定確認酒', $invoiceLine->product_name);
        $this->assertSame('請求確定確認酒 表示名', $invoiceLine->display_name);
        $this->assertSame('1500.0000', $invoiceLine->unit_price);
    }

    private function prepareInvoiceDraft(string $quantity = '1.0000', string $unitPrice = '1500.0000'): InvoiceHeader
    {
        [$invoice] = $this->prepareInvoiceDraftWithSources($quantity, $unitPrice);

        return $invoice;
    }

    /**
     * @return array{0: InvoiceHeader, 1: Product, 2: \App\Models\ShipmentHeader}
     */
    private function prepareInvoiceDraftWithSources(string $quantity = '1.0000', string $unitPrice = '1500.0000'): array
    {
        [$customer, $product, $unit] = $this->prepareBaseData($unitPrice);

        $shipment = app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: '2026-05-23',
            billingTargetDate: '2026-05-23',
            lines: [
                new CreateDraftShipmentLineData($product->id, $quantity, $unit->id),
            ],
        ));
        $shipment = app(ApplyDraftShipmentPricingService::class)->apply($shipment);
        $shipment = app(ConfirmShipmentService::class)->confirm($shipment);

        $invoice = app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: '2026-05-31',
            shipmentHeaderIds: [$shipment->id],
        ));

        return [$invoice, $product, $shipment];
    }

    /**
     * @return array{0: Customer, 1: Product, 2: Unit}
     */
    private function prepareBaseData(string $unitPrice = '1500.0000'): array
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
            'customer_code' => 'INV-CONFIRM-CUST-001',
            'name' => '請求確定確認酒店',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'INV-CONFIRM-SAKE-001',
            'product_type' => 'sake',
            'name' => '請求確定確認酒',
            'display_name' => '請求確定確認酒 表示名',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_alcohol' => true,
        ]);

        PriceRule::create([
            'price_list_id' => PriceList::where('code', 'common')->firstOrFail()->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'unit_price' => $unitPrice,
            'priority' => 300,
            'effective_from' => '2026-01-01',
        ]);

        return [$customer, $product, $unit];
    }
}
