<?php

namespace Tests\Feature;

use App\Exceptions\Tax\ConsumptionTaxFilingReportExportException;
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
use App\Services\Tax\ConfirmConsumptionTaxMonthlyFilingService;
use App\Services\Tax\CreateConsumptionTaxMonthlyFilingDraftService;
use App\Services\Tax\GenerateConsumptionTaxFilingReportService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\PriceMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Database\Seeders\TaxMasterSeeder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateConsumptionTaxFilingReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(Filesystem::class)->deleteDirectory(storage_path('app/reports/consumption-tax-filings'));

        parent::tearDown();
    }

    public function test_it_generates_consumption_tax_filing_report_file_and_export_record(): void
    {
        $filing = $this->prepareConfirmedFiling();

        $export = app(GenerateConsumptionTaxFilingReportService::class)->generate(
            filing: $filing,
            reason: 'monthly consumption tax report',
        );

        $absolutePath = storage_path('app/'.$export->file_path);
        $content = file_get_contents($absolutePath);

        $this->assertSame('consumption_tax_filing', $export->report_type);
        $this->assertSame('txt', $export->format);
        $this->assertSame('generated', $export->status);
        $this->assertSame($filing->id, $export->exportable_id);
        $this->assertSame('text/plain', $export->mime_type);
        $this->assertFileExists($absolutePath);
        $this->assertSame(hash('sha256', $content), $export->checksum_sha256);
        $this->assertStringContainsString('Consumption Tax Monthly Filing Report', $content);
        $this->assertStringContainsString('Period: 2026-06-01 - 2026-06-30', $content);
        $this->assertStringContainsString('Total Confirmed Tax Amount: 450.00', $content);
        $this->assertStringContainsString('taxable_standard', $content);

        $this->assertDatabaseHas('report_exports', [
            'id' => $export->id,
            'report_type' => 'consumption_tax_filing',
            'format' => 'txt',
            'exportable_type' => $filing::class,
            'exportable_id' => $filing->id,
            'reason' => 'monthly consumption tax report',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'consumption_tax_filing_report.generated',
            'target_table' => 'consumption_tax_monthly_filings',
            'target_id' => (string) $filing->id,
            'reason' => 'monthly consumption tax report',
        ]);
    }

    public function test_it_keeps_reissued_consumption_tax_reports_as_separate_files(): void
    {
        $filing = $this->prepareConfirmedFiling();

        $first = app(GenerateConsumptionTaxFilingReportService::class)->generate($filing, reason: 'first consumption tax issue');
        $second = app(GenerateConsumptionTaxFilingReportService::class)->generate($filing, reason: 'consumption tax reissue');

        $this->assertNotSame($first->file_path, $second->file_path);
        $this->assertFileExists(storage_path('app/'.$first->file_path));
        $this->assertFileExists(storage_path('app/'.$second->file_path));

        $this->assertDatabaseHas('report_exports', [
            'id' => $first->id,
            'report_type' => 'consumption_tax_filing',
            'reason' => 'first consumption tax issue',
        ]);
        $this->assertDatabaseHas('report_exports', [
            'id' => $second->id,
            'report_type' => 'consumption_tax_filing',
            'reason' => 'consumption tax reissue',
        ]);
    }

    public function test_it_rejects_unconfirmed_filing_report_generation(): void
    {
        $filing = $this->prepareDraftFiling();

        $this->expectException(ConsumptionTaxFilingReportExportException::class);

        app(GenerateConsumptionTaxFilingReportService::class)->generate($filing);
    }

    public function test_it_rejects_unsupported_report_format(): void
    {
        $filing = $this->prepareConfirmedFiling();

        $this->expectException(ConsumptionTaxFilingReportExportException::class);

        app(GenerateConsumptionTaxFilingReportService::class)->generate($filing, 'pdf');
    }

    private function prepareConfirmedFiling(): \App\Models\ConsumptionTaxMonthlyFiling
    {
        $filing = $this->prepareDraftFiling();

        return app(ConfirmConsumptionTaxMonthlyFilingService::class)
            ->confirm($filing->year, $filing->month, 'monthly consumption tax confirmation');
    }

    private function prepareDraftFiling(): \App\Models\ConsumptionTaxMonthlyFiling
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $shipment = app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: '2026-06-01',
            billingTargetDate: '2026-06-01',
            lines: [
                new CreateDraftShipmentLineData($product->id, '3.0000', $unit->id),
            ],
        ));
        $shipment = app(ApplyDraftShipmentPricingService::class)->apply($shipment);
        $shipment = app(ConfirmShipmentService::class)->confirm($shipment);

        $invoice = app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: '2026-06-01',
            shipmentHeaderIds: [$shipment->id],
        ));
        app(ConfirmInvoiceService::class)->confirm($invoice);

        return app(CreateConsumptionTaxMonthlyFilingDraftService::class)->create(2026, 6);
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
            'customer_code' => 'CT-REPORT-CUST-001',
            'name' => 'Consumption tax report customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'CT-REPORT-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Consumption tax report sake',
            'display_name' => 'Consumption tax report sake',
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
}
