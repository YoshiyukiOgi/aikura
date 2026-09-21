<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\InvoiceHeader;
use App\Models\InvoiceLine;
use App\Models\PriceList;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\SettlementReceivableCategory;
use App\Models\ShipmentHeader;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Services\Billing\BillableShipmentQuery;
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
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InvoiceBaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_base_tables_exist(): void
    {
        foreach (['invoice_headers', 'invoice_lines'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table [{$table}] does not exist.");
        }
    }

    public function test_invoice_lines_use_shipment_confirmed_snapshot_columns(): void
    {
        foreach ([
            'shipment_header_id',
            'shipment_line_id',
            'product_code',
            'product_name',
            'display_name',
            'quantity',
            'unit_code',
            'unit_name',
            'unit_price',
            'amount',
            'consumption_tax_category_id',
            'consumption_tax_category_code',
            'consumption_tax_category_name',
            'consumption_taxability',
            'consumption_tax_rate_id',
            'tax_rate',
            'consumption_tax_rate_effective_from',
            'tax_amount',
            'total_amount',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('invoice_lines', $column), "Column [invoice_lines.{$column}] does not exist.");
        }
    }

    public function test_billable_query_includes_confirmed_unbilled_shipments_only(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $confirmed = $this->createConfirmedShipment($customer, $product, $unit, '2026-07-10');
        $draft = $this->createDraftShipment($customer, $product, $unit, '2026-07-11');
        $cancelled = app(CancelShipmentService::class)->cancel(
            $this->createConfirmedShipment($customer, $product, $unit, '2026-07-12'),
            '請求対象外確認',
        );

        $billableIds = app(BillableShipmentQuery::class)
            ->query(customerId: $customer->id, billingTargetFrom: '2026-07-01', billingTargetTo: '2026-07-31')
            ->pluck('id')
            ->all();

        $this->assertContains($confirmed->id, $billableIds);
        $this->assertNotContains($draft->id, $billableIds);
        $this->assertNotContains($cancelled->id, $billableIds);
    }

    public function test_billable_query_uses_access_billing_month_for_migrated_shipments(): void
    {
        [$customer] = $this->prepareBaseData();

        $shipment = ShipmentHeader::create([
            'document_number' => 'ACCESS-BILLING-93604',
            'status' => 'confirmed',
            'customer_id' => $customer->id,
            'transaction_category_id' => $customer->transaction_category_id,
            'settlement_receivable_category_id' => $customer->settlement_receivable_category_id,
            'billing_cycle_id' => $customer->billing_cycle_id,
            'document_date' => '2026-08-28',
            'billing_target_date' => '2026-08-28',
            'legacy_access_document_number' => '93604',
            'legacy_access_billing_year' => 2026,
            'legacy_access_billing_month' => 9,
        ]);

        $august = app(BillableShipmentQuery::class)
            ->query(customerId: $customer->id, billingTargetFrom: '2026-08-01', billingTargetTo: '2026-08-31')
            ->pluck('id')->all();
        $september = app(BillableShipmentQuery::class)
            ->query(customerId: $customer->id, billingTargetFrom: '2026-09-01', billingTargetTo: '2026-09-30')
            ->pluck('id')->all();

        $this->assertNotContains($shipment->id, $august);
        $this->assertContains($shipment->id, $september);
    }

    public function test_billable_query_excludes_already_invoiced_shipment(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();
        $shipment = $this->createConfirmedShipment($customer, $product, $unit, '2026-07-10');
        $shipmentLine = $shipment->lines->first();

        $invoice = InvoiceHeader::create([
            'status' => 'draft',
            'customer_id' => $customer->id,
            'billing_cycle_id' => $customer->billing_cycle_id,
            'invoice_date' => '2026-07-31',
        ]);

        InvoiceLine::create([
            'invoice_header_id' => $invoice->id,
            'shipment_header_id' => $shipment->id,
            'shipment_line_id' => $shipmentLine->id,
            'line_no' => 1,
            'product_id' => $product->id,
            'product_code' => $shipmentLine->confirmed_product_code,
            'product_name' => $shipmentLine->confirmed_product_name,
            'display_name' => $shipmentLine->confirmed_display_name,
            'quantity' => $shipmentLine->confirmed_quantity,
            'unit_code' => $shipmentLine->confirmed_unit_code,
            'unit_name' => $shipmentLine->confirmed_unit_name,
            'unit_price' => $shipmentLine->confirmed_unit_price,
            'amount' => '3000.00',
            'total_amount' => '3000.00',
        ]);

        $this->assertSame([], app(BillableShipmentQuery::class)->query($customer->id)->pluck('id')->all());
    }

    public function test_invoice_header_relations_work(): void
    {
        [$customer] = $this->prepareBaseData();

        $invoice = InvoiceHeader::create([
            'status' => 'draft',
            'customer_id' => $customer->id,
            'billing_cycle_id' => $customer->billing_cycle_id,
            'invoice_date' => '2026-07-31',
            'billing_period_start' => '2026-07-01',
            'billing_period_end' => '2026-07-31',
        ]);

        $this->assertSame($customer->id, $invoice->customer->id);
        $this->assertSame($customer->billing_cycle_id, $invoice->billingCycle->id);
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
        \App\Models\AppSetting::setValue('operational_start_date', '2026-01-01');

        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();
        $unit = Unit::where('code', 'bottle')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'INV-CUST-001',
            'name' => '請求確認酒店',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'INV-SAKE-001',
            'product_type' => 'sake',
            'name' => '請求確認酒',
            'display_name' => '請求確認酒 表示名',
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

    private function createDraftShipment(Customer $customer, Product $product, Unit $unit, string $billingTargetDate): ShipmentHeader
    {
        return app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: $billingTargetDate,
            billingTargetDate: $billingTargetDate,
            lines: [
                new CreateDraftShipmentLineData($product->id, '2.0000', $unit->id),
            ],
        ));
    }

    private function createConfirmedShipment(Customer $customer, Product $product, Unit $unit, string $billingTargetDate): ShipmentHeader
    {
        $shipment = $this->createDraftShipment($customer, $product, $unit, $billingTargetDate);
        $shipment = app(ApplyDraftShipmentPricingService::class)->apply($shipment);

        return app(ConfirmShipmentService::class)->confirm($shipment);
    }
}
