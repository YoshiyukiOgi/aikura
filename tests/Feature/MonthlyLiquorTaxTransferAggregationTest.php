<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\InvoiceHeader;
use App\Models\InvoiceLine;
use App\Models\PriceList;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\SalesReturnHeader;
use App\Models\SalesReturnLine;
use App\Models\SettlementReceivableCategory;
use App\Models\ShipmentHeader;
use App\Models\ShipmentLiquorTaxEvidence;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Services\Shipment\ApplyDraftShipmentPricingService;
use App\Services\Shipment\CancelShipmentService;
use App\Services\Shipment\ConfirmShipmentService;
use App\Services\Shipment\CreateDraftShipmentData;
use App\Services\Shipment\CreateDraftShipmentLineData;
use App\Services\Shipment\CreateDraftShipmentService;
use App\Services\Tax\AggregateMonthlyLiquorTaxTransfersService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\PriceMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Database\Seeders\TaxMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyLiquorTaxTransferAggregationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_aggregates_confirmed_shipments_by_liquor_tax_transfer_month(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $this->confirmShipment($customer, $product, $unit, '2026-05-31', '2026-06-01', '3.0000');
        $this->confirmShipment($customer, $product, $unit, '2026-06-15', '2026-06-15', '2.0000');

        $maySummaries = app(AggregateMonthlyLiquorTaxTransfersService::class)->aggregate(2026, 5);
        $juneSummaries = app(AggregateMonthlyLiquorTaxTransfersService::class)->aggregate(2026, 6);

        $this->assertCount(0, $maySummaries);
        $this->assertCount(1, $juneSummaries);

        $summary = $juneSummaries->first();

        $this->assertSame(2026, $summary->year);
        $this->assertSame(6, $summary->month);
        $this->assertSame('2026-06-01', $summary->periodStart->toDateString());
        $this->assertSame('2026-06-30', $summary->periodEnd->toDateString());
        $this->assertSame('seishu', $summary->liquorTaxCategoryCode);
        $this->assertSame('taxable', $summary->liquorTaxability);
        $this->assertSame('fixed_per_kl', $summary->calculationMethod);
        $this->assertSame('100000.0000', $summary->taxPerKl);
        $this->assertSame('0.0000', $summary->reductionRate);
        $this->assertSame(15, $summary->reportingAlcoholPercentage);
        $this->assertSame('0.003600', $summary->taxableKl);
        $this->assertSame('360.00', $summary->estimatedAmount);
        $this->assertSame(2, $summary->shipmentCount);
        $this->assertSame(2, $summary->lineCount);
    }

    public function test_it_groups_transfers_by_reporting_alcohol_percentage_with_fraction_flooring(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $this->confirmShipment($customer, $product, $unit, '2026-06-01', '2026-06-01', '1.0000');

        $product->update(['alcohol_percentage' => '16.50']);
        $product->refresh();
        $this->confirmShipment($customer, $product, $unit, '2026-06-02', '2026-06-02', '1.0000');

        $summaries = app(AggregateMonthlyLiquorTaxTransfersService::class)->aggregate(2026, 6);

        $this->assertCount(2, $summaries);
        $this->assertSame([16, 15], $summaries->pluck('reportingAlcoholPercentage')->values()->all());
        $this->assertSame(['0.000720', '0.000720'], $summaries->pluck('taxableKl')->values()->all());
    }

    public function test_it_falls_back_to_document_date_when_transfer_date_is_empty(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $this->confirmShipment($customer, $product, $unit, '2026-05-23', null, '3.0000');

        $summaries = app(AggregateMonthlyLiquorTaxTransfersService::class)->aggregate(2026, 5);

        $this->assertCount(1, $summaries);
        $this->assertSame('0.002160', $summaries->first()->taxableKl);
        $this->assertSame('216.00', $summaries->first()->estimatedAmount);
    }

    public function test_it_excludes_cancelled_shipments(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $shipment = $this->confirmShipment($customer, $product, $unit, '2026-05-23', '2026-05-23', '3.0000');

        app(CancelShipmentService::class)->cancel($shipment, 'cancel aggregation test');

        $summaries = app(AggregateMonthlyLiquorTaxTransfersService::class)->aggregate(2026, 5);

        $this->assertCount(0, $summaries);
    }

    public function test_it_keeps_export_volume_as_zero_tax_with_confirmed_treatment_snapshot(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();
        $export = SettlementReceivableCategory::where('code', 'direct_export')->firstOrFail();
        $customer->update(['settlement_receivable_category_id' => $export->id]);
        $customer->unsetRelation('settlementReceivableCategory');

        $shipment = $this->confirmShipment($customer, $product, $unit, '2026-06-20', '2026-06-20', '3.0000');
        $line = $shipment->lines->first();

        $this->assertSame('export_exempt', $shipment->confirmed_liquor_tax_treatment);
        $this->assertSame('export_exempt', $shipment->confirmed_consumption_tax_treatment);
        $this->assertTrue($shipment->confirmed_requires_evidence);
        $this->assertSame('export_exempt_liquor', $line->confirmed_liquor_tax_category_code);
        $this->assertSame('0.002160', $line->confirmed_liquor_taxable_kl);
        $this->assertSame('0.00', $line->confirmed_liquor_tax_estimated_amount);
        $this->assertSame('export_exempt', $line->confirmed_consumption_tax_category_code);

        $summary = app(AggregateMonthlyLiquorTaxTransfersService::class)->aggregate(2026, 6)->sole();
        $this->assertSame('export_exempt', $summary->taxTreatment);
        $this->assertSame('0.002160', $summary->taxableKl);
        $this->assertSame('0.00', $summary->grossTaxAmount);
        $this->assertTrue($summary->requiresReview);

        ShipmentLiquorTaxEvidence::create([
            'shipment_header_id' => $shipment->id,
            'tax_treatment' => 'export_exempt',
            'status' => 'confirmed',
            'evidence_reference' => 'EXP-2026-001',
            'evidence_date' => '2026-06-25',
            'destination' => 'United States',
            'exporter_type' => 'direct',
            'confirmed_at' => now(),
        ]);

        $confirmed = app(AggregateMonthlyLiquorTaxTransfersService::class)->aggregate(2026, 6)->sole();
        $this->assertFalse($confirmed->requiresReview);
        $this->assertSame('confirmed', $confirmed->sources[0]->evidenceStatus);
        $this->assertSame('EXP-2026-001', $confirmed->sources[0]->evidenceReference);
    }

    public function test_return_is_deducted_only_after_explicit_liquor_tax_review(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();
        $shipment = $this->confirmShipment($customer, $product, $unit, '2026-05-20', '2026-05-20', '3.0000');
        $shipmentLine = $shipment->lines->firstOrFail();

        $invoice = InvoiceHeader::create([
            'invoice_number' => 'INV-LT-RETURN-001',
            'status' => 'confirmed',
            'customer_id' => $customer->id,
            'billing_cycle_id' => $customer->billing_cycle_id,
            'invoice_date' => '2026-05-31',
            'confirmed_at' => now(),
        ]);
        $invoiceLine = InvoiceLine::create([
            'invoice_header_id' => $invoice->id,
            'shipment_header_id' => $shipment->id,
            'shipment_line_id' => $shipmentLine->id,
            'line_no' => 1,
            'product_id' => $product->id,
            'product_code' => $product->product_code,
            'product_name' => $product->name,
            'display_name' => $product->display_name,
            'quantity' => '3.0000',
            'unit_code' => $unit->code,
            'unit_name' => $unit->name,
            'unit_price' => '1500.0000',
        ]);
        $return = SalesReturnHeader::create([
            'return_number' => 'RET-LT-001',
            'status' => 'credit_drafted',
            'customer_id' => $customer->id,
            'return_date' => '2026-06-10',
            'credited_at' => now(),
            'reason' => 'partial return',
        ]);
        $returnLine = SalesReturnLine::create([
            'sales_return_header_id' => $return->id,
            'source_invoice_line_id' => $invoiceLine->id,
            'source_shipment_header_id' => $shipment->id,
            'source_shipment_line_id' => $shipmentLine->id,
            'line_no' => 1,
            'product_id' => $product->id,
            'product_code' => $product->product_code,
            'product_name' => $product->name,
            'display_name' => $product->display_name,
            'quantity' => '1.0000',
            'unit_code' => $unit->code,
            'unit_name' => $unit->name,
            'unit_price' => '1500.0000',
            'amount' => '1500.00',
            'tax_amount' => '150.00',
            'total_amount' => '1650.00',
            'stock_action' => 'return_dedicated_stock',
            'liquor_tax_return_treatment' => 'review',
        ]);

        $pending = app(AggregateMonthlyLiquorTaxTransfersService::class)->aggregate(2026, 6)->sole();
        $this->assertSame('review', $pending->taxTreatment);
        $this->assertSame('0.000000', $pending->taxableKl);
        $this->assertTrue($pending->requiresReview);

        $returnLine->update([
            'liquor_tax_return_treatment' => 'eligible',
            'liquor_tax_return_reason' => 'returned to manufacturing site',
            'liquor_tax_reviewed_at' => now(),
        ]);

        $deduction = app(AggregateMonthlyLiquorTaxTransfersService::class)->aggregate(2026, 6)->sole();
        $this->assertSame('return', $deduction->taxTreatment);
        $this->assertSame('-0.000720', $deduction->taxableKl);
        $this->assertSame('-72.00', $deduction->estimatedAmount);
        $this->assertFalse($deduction->requiresReview);
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
            'customer_code' => 'LT-AGG-CUST-001',
            'name' => 'Liquor tax aggregation customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'LT-AGG-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Liquor tax aggregation sake',
            'display_name' => 'Liquor tax aggregation sake',
            'base_unit_id' => $bottle->id,
            'sales_unit_id' => $bottle->id,
            'inventory_unit_id' => $bottle->id,
            'capacity_value' => '720.0000',
            'capacity_unit_id' => $milliliter->id,
            'alcohol_percentage' => '15.50',
            'is_alcohol' => true,
            'is_inventory_managed' => false,
        ]);

        $priceList = PriceList::where('code', 'common')->firstOrFail();
        PriceRule::create([
            'price_list_id' => $priceList->id,
            'product_id' => $product->id,
            'unit_id' => $bottle->id,
            'unit_price' => '1500.0000',
            'priority' => 300,
            'effective_from' => '2026-01-01',
        ]);

        return [$customer, $product, $bottle];
    }

    private function confirmShipment(
        Customer $customer,
        Product $product,
        Unit $unit,
        string $documentDate,
        ?string $liquorTaxTransferDate,
        string $quantity,
    ): ShipmentHeader {
        $shipment = app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: $documentDate,
            liquorTaxTransferDate: $liquorTaxTransferDate,
            lines: [
                new CreateDraftShipmentLineData($product->id, $quantity, $unit->id),
            ],
        ));

        $shipment = app(ApplyDraftShipmentPricingService::class)->apply($shipment);

        return app(ConfirmShipmentService::class)->confirm($shipment);
    }
}
