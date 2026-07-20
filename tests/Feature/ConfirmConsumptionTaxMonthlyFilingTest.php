<?php

namespace Tests\Feature;

use App\Exceptions\Tax\ClosedConsumptionTaxFilingPeriodException;
use App\Exceptions\Tax\ConsumptionTaxMonthlyFilingConfirmationException;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\SettlementReceivableCategory;
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
use App\Services\Tax\ConfirmConsumptionTaxMonthlyFilingService;
use App\Services\Tax\CreateConsumptionTaxMonthlyFilingDraftService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\PriceMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Database\Seeders\TaxMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfirmConsumptionTaxMonthlyFilingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_confirms_monthly_filing_and_freezes_confirmed_tax_amounts(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $this->createConfirmedInvoice($customer, $product, $unit, '2026-06-01', '2026-06-01', '3.0000');
        app(CreateConsumptionTaxMonthlyFilingDraftService::class)->create(2026, 6);

        $filing = app(ConfirmConsumptionTaxMonthlyFilingService::class)
            ->confirm(2026, 6, 'monthly consumption tax filing confirmation');

        $this->assertSame('confirmed', $filing->status);
        $this->assertSame('450.00', $filing->total_tax_amount);
        $this->assertSame('450.00', $filing->total_confirmed_tax_amount);
        $this->assertNotNull($filing->confirmed_at);
        $this->assertSame('monthly consumption tax filing confirmation', $filing->reason);

        $line = $filing->lines->first();
        $this->assertSame('450.00', $line->tax_amount);
        $this->assertSame('450.00', $line->confirmed_tax_amount);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'consumption_tax_monthly_filing.confirmed',
            'target_table' => 'consumption_tax_monthly_filings',
            'target_id' => (string) $filing->id,
            'reason' => 'monthly consumption tax filing confirmation',
        ]);
    }

    public function test_it_rejects_confirmation_without_draft(): void
    {
        $this->expectException(ConsumptionTaxMonthlyFilingConfirmationException::class);

        app(ConfirmConsumptionTaxMonthlyFilingService::class)
            ->confirm(2026, 6, 'monthly consumption tax filing confirmation');
    }

    public function test_it_rejects_empty_reason(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $this->createConfirmedInvoice($customer, $product, $unit, '2026-06-01', '2026-06-01', '3.0000');
        app(CreateConsumptionTaxMonthlyFilingDraftService::class)->create(2026, 6);

        $this->expectException(ConsumptionTaxMonthlyFilingConfirmationException::class);

        app(ConfirmConsumptionTaxMonthlyFilingService::class)->confirm(2026, 6, ' ');
    }

    public function test_it_rejects_already_confirmed_filing(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $this->createConfirmedInvoice($customer, $product, $unit, '2026-06-01', '2026-06-01', '3.0000');
        app(CreateConsumptionTaxMonthlyFilingDraftService::class)->create(2026, 6);
        app(ConfirmConsumptionTaxMonthlyFilingService::class)->confirm(2026, 6, 'first confirmation');

        $this->expectException(ConsumptionTaxMonthlyFilingConfirmationException::class);

        app(ConfirmConsumptionTaxMonthlyFilingService::class)->confirm(2026, 6, 'second confirmation');
    }

    public function test_it_rejects_invoice_confirmation_in_confirmed_consumption_tax_period(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $this->createConfirmedInvoice($customer, $product, $unit, '2026-06-01', '2026-06-01', '3.0000');
        app(CreateConsumptionTaxMonthlyFilingDraftService::class)->create(2026, 6);
        app(ConfirmConsumptionTaxMonthlyFilingService::class)->confirm(2026, 6, 'monthly consumption tax close');

        $invoice = $this->createDraftInvoice($customer, $product, $unit, '2026-06-20', '2026-06-20', '1.0000');

        $this->expectException(ClosedConsumptionTaxFilingPeriodException::class);

        app(ConfirmInvoiceService::class)->confirm($invoice);
    }

    public function test_it_rejects_confirmed_invoice_cancellation_in_confirmed_consumption_tax_period(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $invoice = $this->createConfirmedInvoice($customer, $product, $unit, '2026-06-01', '2026-06-01', '3.0000');
        app(CreateConsumptionTaxMonthlyFilingDraftService::class)->create(2026, 6);
        app(ConfirmConsumptionTaxMonthlyFilingService::class)->confirm(2026, 6, 'monthly consumption tax close');

        $this->expectException(ClosedConsumptionTaxFilingPeriodException::class);

        app(CancelInvoiceService::class)->cancel($invoice, 'cancel after consumption tax close');
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
            TaxMasterSeeder::class,
            ShipmentMasterSeeder::class,
        ]);

        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();
        $bottle = Unit::where('code', 'bottle')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'CT-CONFIRM-CUST-001',
            'name' => 'Consumption tax confirmation customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'CT-CONFIRM-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Consumption tax confirmation sake',
            'display_name' => 'Consumption tax confirmation sake',
            'base_unit_id' => $bottle->id,
            'sales_unit_id' => $bottle->id,
            'inventory_unit_id' => $bottle->id,
            'is_alcohol' => true,
        ]);

        PriceRule::create([
            'price_list_id' => PriceList::where('code', 'common')->firstOrFail()->id,
            'product_id' => $product->id,
            'unit_id' => $bottle->id,
            'unit_price' => '1500.0000',
            'priority' => 300,
            'effective_from' => '2026-01-01',
        ]);

        return [$customer, $product, $bottle];
    }

    private function createConfirmedInvoice(
        Customer $customer,
        Product $product,
        Unit $unit,
        string $documentDate,
        string $invoiceDate,
        string $quantity,
    ): \App\Models\InvoiceHeader {
        return app(ConfirmInvoiceService::class)->confirm(
            $this->createDraftInvoice($customer, $product, $unit, $documentDate, $invoiceDate, $quantity),
        );
    }

    private function createDraftInvoice(
        Customer $customer,
        Product $product,
        Unit $unit,
        string $documentDate,
        string $invoiceDate,
        string $quantity,
    ): \App\Models\InvoiceHeader {
        $shipment = app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: $documentDate,
            billingTargetDate: $documentDate,
            lines: [
                new CreateDraftShipmentLineData($product->id, $quantity, $unit->id),
            ],
        ));
        $shipment = app(ApplyDraftShipmentPricingService::class)->apply($shipment);
        $shipment = app(ConfirmShipmentService::class)->confirm($shipment);

        return app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: $invoiceDate,
            shipmentHeaderIds: [$shipment->id],
        ));
    }
}
