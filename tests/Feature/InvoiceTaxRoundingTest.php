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

class InvoiceTaxRoundingTest extends TestCase
{
    use RefreshDatabase;

    public function test_line_unit_tax_rounding_calculates_each_line_tax(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData('TAX-ROUND-LINE', 'line', 'round');

        $invoice = $this->createInvoiceWithTwoLines($customer, $product, $unit);

        $this->assertSame('line', $invoice->tax_calculation_unit);
        $this->assertSame('round', $invoice->tax_rounding_method);
        $this->assertSame('66.66', $invoice->subtotal_amount);
        $this->assertSame('6.66', $invoice->tax_amount);
        $this->assertSame('73.32', $invoice->total_amount);
        $this->assertSame(['3.33', '3.33'], $invoice->lines->pluck('tax_amount')->all());
    }

    public function test_invoice_unit_tax_rounding_calculates_tax_by_rate_group(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData('TAX-ROUND-INVOICE', 'invoice', 'round');

        $invoice = $this->createInvoiceWithTwoLines($customer, $product, $unit);

        $this->assertSame('invoice', $invoice->tax_calculation_unit);
        $this->assertSame('round', $invoice->tax_rounding_method);
        $this->assertSame('66.66', $invoice->subtotal_amount);
        $this->assertSame('6.67', $invoice->tax_amount);
        $this->assertSame('73.33', $invoice->total_amount);
        $this->assertSame(['3.33', '3.34'], $invoice->lines->pluck('tax_amount')->all());
    }

    public function test_invoice_unit_tax_rounding_separates_access_lines_with_no_tax_rate_id_by_saved_rate(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData('TAX-ROUND-ACCESS', 'invoice', 'round');

        $invoice = $this->createInvoiceWithTwoLines($customer, $product, $unit, ['0.0800', '0.1000']);

        $this->assertSame('66.66', $invoice->subtotal_amount);
        $this->assertSame('6.00', $invoice->tax_amount);
        $this->assertSame('72.66', $invoice->total_amount);
        $this->assertSame(['2.67', '3.33'], $invoice->lines->pluck('tax_amount')->all());
        $this->assertSame(['0.0800', '0.1000'], $invoice->lines->pluck('tax_rate')->all());
    }

    public function test_invoice_unit_tax_uses_access_saved_header_tax_for_imported_shipments(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData('TAX-ROUND-ACCESS-SAVED', 'invoice', 'round');

        $invoice = $this->createInvoiceWithTwoLines($customer, $product, $unit, ['0.0800', '0.1000'], '5.99');

        $this->assertSame('66.66', $invoice->subtotal_amount);
        $this->assertSame('5.99', $invoice->tax_amount);
        $this->assertSame('72.65', $invoice->total_amount);
        $this->assertSame(['2.66', '3.33'], $invoice->lines->pluck('tax_amount')->all());
    }

    public function test_tax_rounding_method_floor_and_ceil_are_supported(): void
    {
        [$floorCustomer, $floorProduct, $floorUnit] = $this->prepareBaseData('TAX-ROUND-FLOOR', 'invoice', 'floor');
        [$ceilCustomer, $ceilProduct, $ceilUnit] = $this->prepareBaseData('TAX-ROUND-CEIL', 'invoice', 'ceil');

        $floorInvoice = $this->createInvoiceWithTwoLines($floorCustomer, $floorProduct, $floorUnit);
        $ceilInvoice = $this->createInvoiceWithTwoLines($ceilCustomer, $ceilProduct, $ceilUnit);

        $this->assertSame('6.66', $floorInvoice->tax_amount);
        $this->assertSame('6.67', $ceilInvoice->tax_amount);
    }

    /** @param list<string>|null $snapshotRates */
    private function createInvoiceWithTwoLines(Customer $customer, Product $product, Unit $unit, ?array $snapshotRates = null, ?string $legacyAccessTaxAmount = null)
    {
        $shipment = app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: '2026-08-23',
            billingTargetDate: '2026-08-23',
            lines: [
                new CreateDraftShipmentLineData($product->id, '1.0000', $unit->id),
                new CreateDraftShipmentLineData($product->id, '1.0000', $unit->id),
            ],
        ));
        $shipment = app(ApplyDraftShipmentPricingService::class)->apply($shipment);
        $shipment = app(ConfirmShipmentService::class)->confirm($shipment);
        $lines = $shipment->lines()->orderBy('line_no')->get();
        if ($snapshotRates !== null) {
            foreach ($lines as $index => $line) {
                $line->update([
                    'confirmed_consumption_tax_rate_id' => null,
                    'confirmed_consumption_tax_rate' => $snapshotRates[$index],
                ]);
            }
        }
        if ($legacyAccessTaxAmount !== null) {
            $shipment->update([
                'legacy_access_document_number' => 'TEST-LEGACY-'.$customer->id,
                'legacy_access_net_amount' => '66.66',
                'legacy_access_consumption_tax_amount' => $legacyAccessTaxAmount,
                'legacy_access_total_amount' => bcadd('66.66', $legacyAccessTaxAmount, 2),
            ]);
            foreach ($lines as $index => $line) {
                $line->update(['legacy_access_consumption_tax_amount' => $index === 0 ? '2.67' : '3.33']);
            }
        }

        return app(CreateInvoiceDraftService::class)
            ->create(new CreateInvoiceDraftData(
                customerId: $customer->id,
                invoiceDate: '2026-08-31',
                shipmentHeaderIds: [$shipment->id],
            ))
            ->load('lines');
    }

    /**
     * @return array{0: Customer, 1: Product, 2: Unit}
     */
    private function prepareBaseData(string $code, string $taxCalculationUnit, string $taxRoundingMethod): array
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
            'customer_code' => $code,
            'name' => $code.' Customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
            'tax_calculation_unit' => $taxCalculationUnit,
            'tax_rounding_method' => $taxRoundingMethod,
        ]);

        $product = Product::create([
            'product_code' => $code.'-PRODUCT',
            'product_type' => 'sake',
            'name' => $code.' Product',
            'display_name' => $code.' Product',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_inventory_managed' => false,
            'is_alcohol' => true,
        ]);

        PriceRule::create([
            'price_list_id' => PriceList::where('code', 'common')->firstOrFail()->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'unit_price' => '33.3333',
            'priority' => 300,
            'effective_from' => '2026-01-01',
        ]);

        return [$customer, $product, $unit];
    }
}
