<?php

namespace Tests\Feature;

use App\Exceptions\Billing\InvoiceReportExportException;
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
use App\Services\Billing\GenerateInvoiceReportService;
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
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

class GenerateInvoiceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(Filesystem::class)->deleteDirectory(storage_path('app/reports/invoices'));

        parent::tearDown();
    }

    public function test_it_generates_invoice_report_file_and_export_record(): void
    {
        $invoice = app(ConfirmInvoiceService::class)->confirm($this->prepareInvoiceDraft());

        $export = app(GenerateInvoiceReportService::class)->generate(
            invoice: $invoice,
            reason: 'monthly invoice report',
        );

        $absolutePath = storage_path('app/'.$export->file_path);
        $content = file_get_contents($absolutePath);

        $this->assertSame('invoice', $export->report_type);
        $this->assertSame('txt', $export->format);
        $this->assertSame('generated', $export->status);
        $this->assertSame($invoice->id, $export->exportable_id);
        $this->assertSame('text/plain', $export->mime_type);
        $this->assertFileExists($absolutePath);
        $this->assertSame(hash('sha256', $content), $export->checksum_sha256);
        $this->assertStringContainsString('Invoice Number: '.$invoice->invoice_number, $content);
        $this->assertStringContainsString('Report Sake 720ml', $content);
        $this->assertStringContainsString('Total: 3300.00', $content);

        $this->assertDatabaseHas('report_exports', [
            'id' => $export->id,
            'report_type' => 'invoice',
            'format' => 'txt',
            'exportable_type' => $invoice::class,
            'exportable_id' => $invoice->id,
            'reason' => 'monthly invoice report',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'invoice_report.generated',
            'target_table' => 'invoice_headers',
            'target_id' => (string) $invoice->id,
            'reason' => 'monthly invoice report',
        ]);
    }

    public function test_it_keeps_reissued_invoice_reports_as_separate_files(): void
    {
        $invoice = app(ConfirmInvoiceService::class)->confirm($this->prepareInvoiceDraft());

        $first = app(GenerateInvoiceReportService::class)->generate($invoice, reason: 'first issue');
        $second = app(GenerateInvoiceReportService::class)->generate($invoice, reason: 'reissue for customer copy');

        $this->assertNotSame($first->file_path, $second->file_path);
        $this->assertFileExists(storage_path('app/'.$first->file_path));
        $this->assertFileExists(storage_path('app/'.$second->file_path));

        $this->assertDatabaseHas('report_exports', [
            'id' => $first->id,
            'report_type' => 'invoice',
            'reason' => 'first issue',
        ]);
        $this->assertDatabaseHas('report_exports', [
            'id' => $second->id,
            'report_type' => 'invoice',
            'reason' => 'reissue for customer copy',
        ]);
    }

    public function test_it_rejects_unconfirmed_invoice_report_generation(): void
    {
        $invoice = $this->prepareInvoiceDraft();

        $this->expectException(InvoiceReportExportException::class);

        app(GenerateInvoiceReportService::class)->generate($invoice);
    }

    public function test_it_rejects_unsupported_report_format(): void
    {
        $invoice = app(ConfirmInvoiceService::class)->confirm($this->prepareInvoiceDraft());

        $this->expectException(InvoiceReportExportException::class);

        app(GenerateInvoiceReportService::class)->generate($invoice, 'pdf');
    }

    private function prepareInvoiceDraft()
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
            invoiceDate: '2026-07-31',
            shipmentHeaderIds: [$shipment->id],
        ));
    }

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
            'customer_code' => 'INV-REPORT-CUST-001',
            'name' => 'Invoice Report Customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'INV-REPORT-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Report Sake',
            'display_name' => 'Report Sake 720ml',
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
