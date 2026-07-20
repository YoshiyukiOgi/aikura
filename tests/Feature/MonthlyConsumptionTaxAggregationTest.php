<?php

namespace Tests\Feature;

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
use App\Services\Tax\AggregateMonthlyConsumptionTaxService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\PriceMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Database\Seeders\TaxMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyConsumptionTaxAggregationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_aggregates_confirmed_invoices_by_invoice_month_and_tax_rate(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $this->createConfirmedInvoice($customer, $product, $unit, '2026-05-31', '2026-06-01', '2.0000');
        $this->createConfirmedInvoice($customer, $product, $unit, '2026-06-15', '2026-06-15', '3.0000');

        $maySummaries = app(AggregateMonthlyConsumptionTaxService::class)->aggregate(2026, 5);
        $juneSummaries = app(AggregateMonthlyConsumptionTaxService::class)->aggregate(2026, 6);

        $this->assertCount(0, $maySummaries);
        $this->assertCount(1, $juneSummaries);

        $summary = $juneSummaries->first();

        $this->assertSame(2026, $summary->year);
        $this->assertSame(6, $summary->month);
        $this->assertSame('2026-06-01', $summary->periodStart->toDateString());
        $this->assertSame('2026-06-30', $summary->periodEnd->toDateString());
        $this->assertSame('taxable_standard', $summary->consumptionTaxCategoryCode);
        $this->assertSame('taxable', $summary->consumptionTaxability);
        $this->assertSame('0.1000', $summary->taxRate);
        $this->assertSame('2019-10-01', $summary->rateEffectiveFrom?->toDateString());
        $this->assertSame('7500.00', $summary->taxableAmount);
        $this->assertSame('750.00', $summary->taxAmount);
        $this->assertSame('8250.00', $summary->totalAmount);
        $this->assertSame(2, $summary->invoiceCount);
        $this->assertSame(2, $summary->lineCount);
    }

    public function test_it_excludes_draft_and_cancelled_invoices(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $confirmed = $this->createConfirmedInvoice($customer, $product, $unit, '2026-06-01', '2026-06-01', '1.0000');
        app(CancelInvoiceService::class)->cancel($confirmed, 'exclude cancelled invoice');
        $this->createDraftInvoice($customer, $product, $unit, '2026-06-02', '2026-06-02', '2.0000');

        $summaries = app(AggregateMonthlyConsumptionTaxService::class)->aggregate(2026, 6);

        $this->assertCount(0, $summaries);
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
            'customer_code' => 'CT-AGG-CUST-001',
            'name' => 'Consumption tax aggregation customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'CT-AGG-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Consumption tax aggregation sake',
            'display_name' => 'Consumption tax aggregation sake',
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
