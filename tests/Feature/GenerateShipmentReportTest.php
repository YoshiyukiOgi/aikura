<?php

namespace Tests\Feature;

use App\Exceptions\Shipment\ShipmentReportExportException;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\SettlementReceivableCategory;
use App\Models\ShipmentHeader;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Services\Shipment\ApplyDraftShipmentPricingService;
use App\Services\Shipment\ConfirmShipmentService;
use App\Services\Shipment\CreateDraftShipmentData;
use App\Services\Shipment\CreateDraftShipmentLineData;
use App\Services\Shipment\CreateDraftShipmentService;
use App\Services\Shipment\GenerateShipmentReportService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\PriceMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Database\Seeders\TaxMasterSeeder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateShipmentReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(Filesystem::class)->deleteDirectory(storage_path('app/reports/shipments'));

        parent::tearDown();
    }

    public function test_it_generates_shipment_report_file_and_export_record(): void
    {
        $shipment = app(ConfirmShipmentService::class)->confirm($this->preparePricedDraftShipment());

        $export = app(GenerateShipmentReportService::class)->generate(
            shipment: $shipment,
            reason: 'shipment report issue',
        );

        $absolutePath = storage_path('app/'.$export->file_path);
        $content = file_get_contents($absolutePath);

        $this->assertSame('shipment', $export->report_type);
        $this->assertSame('txt', $export->format);
        $this->assertSame('generated', $export->status);
        $this->assertSame($shipment->id, $export->exportable_id);
        $this->assertSame('text/plain', $export->mime_type);
        $this->assertFileExists($absolutePath);
        $this->assertSame(hash('sha256', $content), $export->checksum_sha256);
        $this->assertStringContainsString('Document Number: '.$shipment->document_number, $content);
        $this->assertStringContainsString('Report Shipment Sake 720ml', $content);
        $this->assertStringContainsString('Subtotal: 3000.00', $content);
        $this->assertStringContainsString('Consumption Tax: 300.00', $content);
        $this->assertStringContainsString('Liquor Tax Estimate: 144.00', $content);

        $this->assertDatabaseHas('report_exports', [
            'id' => $export->id,
            'report_type' => 'shipment',
            'format' => 'txt',
            'exportable_type' => $shipment::class,
            'exportable_id' => $shipment->id,
            'reason' => 'shipment report issue',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'shipment_report.generated',
            'target_table' => 'shipment_headers',
            'target_id' => (string) $shipment->id,
            'reason' => 'shipment report issue',
        ]);
    }

    public function test_it_rejects_unconfirmed_shipment_report_generation(): void
    {
        $shipment = $this->preparePricedDraftShipment();

        $this->expectException(ShipmentReportExportException::class);

        app(GenerateShipmentReportService::class)->generate($shipment);
    }

    public function test_it_rejects_unsupported_report_format(): void
    {
        $shipment = app(ConfirmShipmentService::class)->confirm($this->preparePricedDraftShipment());

        $this->expectException(ShipmentReportExportException::class);

        app(GenerateShipmentReportService::class)->generate($shipment, 'pdf');
    }

    private function preparePricedDraftShipment(): ShipmentHeader
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $shipment = app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: '2026-05-23',
            billingTargetDate: '2026-05-23',
            liquorTaxTransferDate: '2026-05-23',
            lines: [
                new CreateDraftShipmentLineData($product->id, '2.0000', $unit->id),
            ],
        ));

        return app(ApplyDraftShipmentPricingService::class)->apply($shipment);
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
            'customer_code' => 'SHIP-REPORT-CUST-001',
            'name' => 'Shipment Report Customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'SHIP-REPORT-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Report Shipment Sake',
            'display_name' => 'Report Shipment Sake 720ml',
            'base_unit_id' => $bottle->id,
            'sales_unit_id' => $bottle->id,
            'inventory_unit_id' => $bottle->id,
            'capacity_value' => '720.0000',
            'capacity_unit_id' => $milliliter->id,
            'alcohol_percentage' => '15.50',
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
