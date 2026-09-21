<?php

namespace Tests\Feature;

use App\Exceptions\Tax\ConsumptionTaxMonthlyFilingDraftException;
use App\Models\BillingCycle;
use App\Models\Customer;
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
use App\Services\Tax\CreateConsumptionTaxMonthlyFilingDraftService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\PriceMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Database\Seeders\TaxMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConsumptionTaxMonthlyFilingDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumption_tax_monthly_filing_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('consumption_tax_monthly_filings'));
        $this->assertTrue(Schema::hasTable('consumption_tax_monthly_filing_lines'));

        foreach ([
            'status',
            'year',
            'month',
            'period_start',
            'period_end',
            'total_taxable_amount',
            'total_tax_amount',
            'total_confirmed_tax_amount',
            'total_amount',
            'invoice_count',
            'line_count',
            'calculated_at',
            'confirmed_at',
            'closed_at',
            'reason',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('consumption_tax_monthly_filings', $column),
                "Column [consumption_tax_monthly_filings.{$column}] does not exist.",
            );
        }

        foreach ([
            'consumption_tax_monthly_filing_id',
            'line_no',
            'consumption_tax_category_id',
            'consumption_tax_category_code',
            'consumption_tax_category_name',
            'consumption_taxability',
            'consumption_tax_rate_id',
            'tax_rate',
            'consumption_tax_rate_effective_from',
            'taxable_amount',
            'tax_amount',
            'confirmed_tax_amount',
            'total_amount',
            'invoice_count',
            'line_count',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('consumption_tax_monthly_filing_lines', $column),
                "Column [consumption_tax_monthly_filing_lines.{$column}] does not exist.",
            );
        }
    }

    public function test_it_creates_monthly_filing_draft_from_consumption_tax_summary(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $this->createConfirmedInvoice($customer, $product, $unit, '2026-07-01', '2026-07-01', '2.0000');
        $this->createConfirmedInvoice($customer, $product, $unit, '2026-07-15', '2026-07-15', '3.0000');

        $filing = app(CreateConsumptionTaxMonthlyFilingDraftService::class)
            ->create(2026, 7, 'monthly consumption tax draft');

        $this->assertSame('draft', $filing->status);
        $this->assertSame(2026, $filing->year);
        $this->assertSame(7, $filing->month);
        $this->assertSame('2026-07-01', $filing->period_start->toDateString());
        $this->assertSame('2026-07-31', $filing->period_end->toDateString());
        $this->assertSame('7500.00', $filing->total_taxable_amount);
        $this->assertSame('750.00', $filing->total_tax_amount);
        $this->assertSame('8250.00', $filing->total_amount);
        $this->assertSame(2, $filing->invoice_count);
        $this->assertSame(2, $filing->line_count);
        $this->assertSame('monthly consumption tax draft', $filing->reason);
        $this->assertNotNull($filing->calculated_at);

        $this->assertCount(1, $filing->lines);
        $line = $filing->lines->first();

        $this->assertSame(1, $line->line_no);
        $this->assertSame('taxable_standard', $line->consumption_tax_category_code);
        $this->assertSame('taxable', $line->consumption_taxability);
        $this->assertSame('0.1000', $line->tax_rate);
        $this->assertSame('2019-10-01', $line->consumption_tax_rate_effective_from?->toDateString());
        $this->assertSame('7500.00', $line->taxable_amount);
        $this->assertSame('750.00', $line->tax_amount);
        $this->assertSame('8250.00', $line->total_amount);
    }

    public function test_it_updates_existing_draft_for_same_month(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $this->createConfirmedInvoice($customer, $product, $unit, '2026-07-01', '2026-07-01', '1.0000');
        $first = app(CreateConsumptionTaxMonthlyFilingDraftService::class)->create(2026, 7);

        $this->createConfirmedInvoice($customer, $product, $unit, '2026-07-02', '2026-07-02', '2.0000');
        $second = app(CreateConsumptionTaxMonthlyFilingDraftService::class)->create(2026, 7);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('4500.00', $second->total_taxable_amount);
        $this->assertSame('450.00', $second->total_tax_amount);
        $this->assertSame('4950.00', $second->total_amount);
        $this->assertSame(2, $second->invoice_count);
        $this->assertSame(2, $second->line_count);
        $this->assertCount(1, $second->lines);
    }

    public function test_it_rejects_recreating_non_draft_month(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $this->createConfirmedInvoice($customer, $product, $unit, '2026-07-01', '2026-07-01', '1.0000');
        $filing = app(CreateConsumptionTaxMonthlyFilingDraftService::class)->create(2026, 7);
        $filing->update(['status' => 'confirmed']);

        $this->expectException(ConsumptionTaxMonthlyFilingDraftException::class);

        app(CreateConsumptionTaxMonthlyFilingDraftService::class)->create(2026, 7);
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
        \App\Models\AppSetting::setValue('operational_start_date', '2026-01-01');

        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();
        $bottle = Unit::where('code', 'bottle')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'CT-FILING-CUST-001',
            'name' => 'Consumption tax filing customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'CT-FILING-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Consumption tax filing sake',
            'display_name' => 'Consumption tax filing sake',
            'base_unit_id' => $bottle->id,
            'sales_unit_id' => $bottle->id,
            'inventory_unit_id' => $bottle->id,
            'is_alcohol' => true,
            'is_inventory_managed' => false,
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

        $invoice = app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: $invoiceDate,
            shipmentHeaderIds: [$shipment->id],
        ));

        return app(ConfirmInvoiceService::class)->confirm($invoice);
    }
}
