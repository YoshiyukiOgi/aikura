<?php

namespace Tests\Feature;

use App\Exceptions\Tax\LiquorTaxFilingReportExportException;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\LiquorTaxMonthlyFiling;
use App\Models\PriceList;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\SettlementReceivableCategory;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Services\Shipment\ApplyDraftShipmentPricingService;
use App\Services\Shipment\ConfirmShipmentService;
use App\Services\Shipment\CreateDraftShipmentData;
use App\Services\Shipment\CreateDraftShipmentLineData;
use App\Services\Shipment\CreateDraftShipmentService;
use App\Services\Tax\ConfirmLiquorTaxMonthlyFilingService;
use App\Services\Tax\CreateLiquorTaxMonthlyFilingDraftService;
use App\Services\Tax\GenerateLiquorTaxFilingReportService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\PriceMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Database\Seeders\TaxMasterSeeder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class GenerateLiquorTaxFilingReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(Filesystem::class)->deleteDirectory(storage_path('app/reports/liquor-tax-filings'));

        parent::tearDown();
    }

    public function test_it_generates_liquor_tax_filing_report_file_and_export_record(): void
    {
        $filing = $this->prepareConfirmedFiling();

        $export = app(GenerateLiquorTaxFilingReportService::class)->generate(
            filing: $filing,
            reason: 'monthly liquor tax report',
        );

        $absolutePath = storage_path('app/'.$export->file_path);
        $content = file_get_contents($absolutePath);

        $this->assertSame('liquor_tax_filing', $export->report_type);
        $this->assertSame('txt', $export->format);
        $this->assertSame('generated', $export->status);
        $this->assertSame($filing->id, $export->exportable_id);
        $this->assertSame('text/plain', $export->mime_type);
        $this->assertFileExists($absolutePath);
        $this->assertSame(hash('sha256', $content), $export->checksum_sha256);
        $this->assertStringContainsString('Liquor Tax Monthly Filing Report', $content);
        $this->assertStringContainsString('Period: 2026-06-01 - 2026-06-30', $content);
        $this->assertStringContainsString('Total Confirmed Amount: 100.00', $content);

        $this->assertDatabaseHas('report_exports', [
            'id' => $export->id,
            'report_type' => 'liquor_tax_filing',
            'format' => 'txt',
            'exportable_type' => $filing::class,
            'exportable_id' => $filing->id,
            'reason' => 'monthly liquor tax report',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'liquor_tax_filing_report.generated',
            'target_table' => 'liquor_tax_monthly_filings',
            'target_id' => (string) $filing->id,
            'reason' => 'monthly liquor tax report',
        ]);
    }

    public function test_it_keeps_reissued_liquor_tax_reports_as_separate_files(): void
    {
        $filing = $this->prepareConfirmedFiling();

        $first = app(GenerateLiquorTaxFilingReportService::class)->generate($filing, reason: 'first liquor tax issue');
        $second = app(GenerateLiquorTaxFilingReportService::class)->generate($filing, reason: 'liquor tax reissue');

        $this->assertNotSame($first->file_path, $second->file_path);
        $this->assertFileExists(storage_path('app/'.$first->file_path));
        $this->assertFileExists(storage_path('app/'.$second->file_path));

        $this->assertDatabaseHas('report_exports', [
            'id' => $first->id,
            'report_type' => 'liquor_tax_filing',
            'reason' => 'first liquor tax issue',
        ]);
        $this->assertDatabaseHas('report_exports', [
            'id' => $second->id,
            'report_type' => 'liquor_tax_filing',
            'reason' => 'liquor tax reissue',
        ]);
    }

    public function test_it_rejects_unconfirmed_filing_report_generation(): void
    {
        $filing = $this->prepareDraftFiling();

        $this->expectException(LiquorTaxFilingReportExportException::class);

        app(GenerateLiquorTaxFilingReportService::class)->generate($filing);
    }

    public function test_it_rejects_unsupported_report_format(): void
    {
        $filing = $this->prepareConfirmedFiling();

        $this->expectException(LiquorTaxFilingReportExportException::class);

        app(GenerateLiquorTaxFilingReportService::class)->generate($filing, 'csv');
    }

    public function test_it_generates_xlsx_and_pdf_confirmation_sheets(): void
    {
        $filing = $this->prepareConfirmedFiling();

        $xlsx = app(GenerateLiquorTaxFilingReportService::class)->generate($filing, 'xlsx', 'e-Tax transfer workbook');
        $pdf = app(GenerateLiquorTaxFilingReportService::class)->generate($filing, 'pdf', 'paper confirmation copy');

        $xlsxPath = storage_path('app/'.$xlsx->file_path);
        $pdfPath = storage_path('app/'.$pdf->file_path);
        $book = IOFactory::load($xlsxPath);
        $this->assertSame(['e-Tax転記確認表', '根拠明細', '軽減計算根拠'], $book->getSheetNames());
        $this->assertSame('酒税納税申告 e-Tax転記確認表', $book->getSheet(0)->getCell('A1')->getValue());
        $this->assertSame(100.0, $book->getSheet(0)->getCell('D11')->getValue());
        $this->assertSame('legacy_scheme', $book->getSheetByName('軽減計算根拠')->getCell('B2')->getValue());
        $this->assertSame('legacy_quantity_limit', $book->getSheetByName('軽減計算根拠')->getCell('H2')->getValue());
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $xlsx->mime_type);
        $this->assertSame('application/pdf', $pdf->mime_type);
        $this->assertStringStartsWith('%PDF-', file_get_contents($pdfPath));
        $this->assertGreaterThan(1000, filesize($pdfPath));
    }

    private function prepareConfirmedFiling(): LiquorTaxMonthlyFiling
    {
        $filing = $this->prepareDraftFiling();

        return app(ConfirmLiquorTaxMonthlyFilingService::class)
            ->confirm($filing->year, $filing->month, 'monthly liquor tax confirmation');
    }

    private function prepareDraftFiling(): LiquorTaxMonthlyFiling
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $shipment = app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: '2026-06-01',
            liquorTaxTransferDate: '2026-06-01',
            lines: [
                new CreateDraftShipmentLineData($product->id, '3.0000', $unit->id),
            ],
        ));
        $shipment = app(ApplyDraftShipmentPricingService::class)->apply($shipment);
        app(ConfirmShipmentService::class)->confirm($shipment);

        return app(CreateLiquorTaxMonthlyFilingDraftService::class)->create(2026, 6);
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
        $milliliter = Unit::where('code', 'milliliter')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'LT-REPORT-CUST-001',
            'name' => 'Liquor tax report customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'LT-REPORT-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Liquor tax report sake',
            'display_name' => 'Liquor tax report sake',
            'base_unit_id' => $bottle->id,
            'sales_unit_id' => $bottle->id,
            'inventory_unit_id' => $bottle->id,
            'capacity_value' => '720.0000',
            'capacity_unit_id' => $milliliter->id,
            'alcohol_percentage' => '15.50',
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
}
