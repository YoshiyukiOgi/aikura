<?php

namespace Tests\Feature;

use App\Exceptions\Billing\InvoiceCancellationException;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\InvoiceHeader;
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

class CancelInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_cancels_confirmed_invoice_with_reason_and_audit_log(): void
    {
        [$invoice] = $this->prepareConfirmedInvoice();

        $cancelled = app(CancelInvoiceService::class)->cancel($invoice, 'billing correction');

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame('billing correction', $cancelled->cancelled_reason);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'invoice.cancelled',
            'target_table' => 'invoice_headers',
            'target_id' => (string) $cancelled->id,
            'reason' => 'billing correction',
        ]);
    }

    public function test_cancelled_invoice_releases_shipments_for_reissue(): void
    {
        [$invoice, $shipment] = $this->prepareConfirmedInvoice();

        app(CancelInvoiceService::class)->cancel($invoice, 'reissue invoice');

        $reissuedDraft = app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $invoice->customer_id,
            invoiceDate: '2026-07-01',
            shipmentHeaderIds: [$shipment->id],
        ));

        $this->assertSame('draft', $reissuedDraft->status);
        $this->assertNotSame($invoice->id, $reissuedDraft->id);
        $this->assertSame($shipment->lines->first()->id, $reissuedDraft->lines->first()->shipment_line_id);
    }

    public function test_it_rejects_empty_cancellation_reason(): void
    {
        [$invoice] = $this->prepareConfirmedInvoice();

        $this->expectException(InvoiceCancellationException::class);

        app(CancelInvoiceService::class)->cancel($invoice, '   ');
    }

    public function test_it_rejects_cancelling_closed_invoice(): void
    {
        [$invoice] = $this->prepareConfirmedInvoice();
        $invoice->forceFill(['status' => 'closed'])->save();

        $this->expectException(InvoiceCancellationException::class);

        app(CancelInvoiceService::class)->cancel($invoice, 'closed invoice cannot be cancelled');
    }

    /**
     * @return array{0: InvoiceHeader, 1: ShipmentHeader}
     */
    private function prepareConfirmedInvoice(): array
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

        $invoice = app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: '2026-07-31',
            shipmentHeaderIds: [$shipment->id],
        ));

        return [app(ConfirmInvoiceService::class)->confirm($invoice), $shipment];
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
            'customer_code' => 'INV-CANCEL-CUST-001',
            'name' => 'Invoice Cancel Customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'INV-CANCEL-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Cancel Sake',
            'display_name' => 'Cancel Sake 720ml',
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
