<?php

namespace Tests\Feature;

use App\Exceptions\Tax\LiquorTaxMonthlyFilingDraftException;
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
use App\Services\Tax\CreateLiquorTaxMonthlyFilingDraftService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\PriceMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Database\Seeders\TaxMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LiquorTaxMonthlyFilingDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_liquor_tax_monthly_filing_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('liquor_tax_monthly_filings'));
        $this->assertTrue(Schema::hasTable('liquor_tax_monthly_filing_lines'));

        foreach ([
            'status',
            'year',
            'month',
            'period_start',
            'period_end',
            'total_taxable_kl',
            'total_estimated_amount',
            'total_confirmed_amount',
            'shipment_count',
            'line_count',
            'calculated_at',
            'confirmed_at',
            'closed_at',
            'reason',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('liquor_tax_monthly_filings', $column),
                "Column [liquor_tax_monthly_filings.{$column}] does not exist.",
            );
        }

        foreach ([
            'liquor_tax_monthly_filing_id',
            'line_no',
            'liquor_tax_category_id',
            'liquor_tax_category_code',
            'liquor_tax_category_name',
            'liquor_taxability',
            'liquor_tax_rule_id',
            'reporting_alcohol_percentage',
            'calculation_method',
            'tax_per_kl',
            'reduction_rate',
            'taxable_kl',
            'estimated_amount',
            'confirmed_amount',
            'cumulative_gross_before',
            'cumulative_gross_after',
            'relief_calculation_basis',
            'shipment_count',
            'line_count',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('liquor_tax_monthly_filing_lines', $column),
                "Column [liquor_tax_monthly_filing_lines.{$column}] does not exist.",
            );
        }
    }

    public function test_it_creates_monthly_filing_draft_from_transfer_summary(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $this->confirmShipment($customer, $product, $unit, '2026-05-31', '2026-06-01', '3.0000');
        $this->confirmShipment($customer, $product, $unit, '2026-06-15', '2026-06-15', '2.0000');

        $filing = app(CreateLiquorTaxMonthlyFilingDraftService::class)
            ->create(2026, 6, 'monthly liquor tax draft');

        $this->assertSame('draft', $filing->status);
        $this->assertSame(2026, $filing->year);
        $this->assertSame(6, $filing->month);
        $this->assertSame('2026-06-01', $filing->period_start->toDateString());
        $this->assertSame('2026-06-30', $filing->period_end->toDateString());
        $this->assertSame('0.003600', $filing->total_taxable_kl);
        $this->assertSame('360.00', $filing->total_gross_tax_amount);
        $this->assertSame('72.00', $filing->total_relief_amount);
        $this->assertSame('200.00', $filing->net_payable_amount);
        $this->assertSame('200.00', $filing->total_estimated_amount);
        $this->assertSame('legacy_scheme', $filing->relief_scheme);
        $this->assertSame(2, $filing->shipment_count);
        $this->assertSame(2, $filing->line_count);
        $this->assertSame('monthly liquor tax draft', $filing->reason);
        $this->assertNotNull($filing->calculated_at);

        $this->assertCount(1, $filing->lines);
        $line = $filing->lines->first();

        $this->assertSame(1, $line->line_no);
        $this->assertSame('seishu', $line->liquor_tax_category_code);
        $this->assertSame('taxable', $line->liquor_taxability);
        $this->assertSame('fixed_per_kl', $line->calculation_method);
        $this->assertSame(15, $line->reporting_alcohol_percentage);
        $this->assertSame('100000.0000', $line->tax_per_kl);
        $this->assertSame('0.2000', $line->reduction_rate);
        $this->assertSame('0.003600', $line->taxable_kl);
        $this->assertSame('360.00', $line->gross_tax_amount);
        $this->assertSame('72.00', $line->relief_amount);
        $this->assertNull($line->cumulative_gross_before);
        $this->assertNull($line->cumulative_gross_after);
        $this->assertSame('legacy_scheme', $line->relief_calculation_basis['scheme']);
        $this->assertSame('0.003600', $line->relief_calculation_basis['eligible_kl_after']);
        $this->assertSame('288.00', $line->estimated_amount);
        $this->assertCount(2, $filing->sources);
        $this->assertSame([15, 15], $filing->sources->pluck('reporting_alcohol_percentage')->all());
    }

    public function test_it_updates_existing_draft_for_same_month(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $this->confirmShipment($customer, $product, $unit, '2026-06-01', '2026-06-01', '1.0000');
        $first = app(CreateLiquorTaxMonthlyFilingDraftService::class)->create(2026, 6);

        $this->confirmShipment($customer, $product, $unit, '2026-06-02', '2026-06-02', '2.0000');
        $second = app(CreateLiquorTaxMonthlyFilingDraftService::class)->create(2026, 6);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('0.002160', $second->total_taxable_kl);
        $this->assertSame('216.00', $second->total_gross_tax_amount);
        $this->assertSame('43.00', $second->total_relief_amount);
        $this->assertSame('100.00', $second->total_estimated_amount);
        $this->assertSame(2, $second->shipment_count);
        $this->assertSame(2, $second->line_count);
        $this->assertCount(1, $second->lines);
    }

    public function test_it_sorts_lines_by_alcohol_percentage_desc_within_same_treatment(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $stronger = $product->replicate();
        $stronger->product_code = 'LT-FILING-SAKE-002';
        $stronger->name = 'Liquor tax filing sake strong';
        $stronger->display_name = 'Liquor tax filing sake strong';
        $stronger->alcohol_percentage = '17.20';
        $stronger->save();

        PriceRule::create([
            'price_list_id' => PriceList::where('code', 'common')->firstOrFail()->id,
            'product_id' => $stronger->id,
            'unit_id' => $unit->id,
            'unit_price' => '1600.0000',
            'priority' => 301,
            'effective_from' => '2026-01-01',
        ]);

        $this->confirmShipment($customer, $product, $unit, '2026-06-10', '2026-06-10', '1.0000');
        $this->confirmShipment($customer, $stronger, $unit, '2026-06-11', '2026-06-11', '1.0000');

        $filing = app(CreateLiquorTaxMonthlyFilingDraftService::class)->create(2026, 6, 'sort by alcohol desc');

        $this->assertSame([17, 15], $filing->lines->pluck('reporting_alcohol_percentage')->all());
        $this->assertSame(['taxable', 'taxable'], $filing->lines->pluck('tax_treatment')->all());
    }

    public function test_it_rejects_recreating_non_draft_month(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $this->confirmShipment($customer, $product, $unit, '2026-06-01', '2026-06-01', '1.0000');
        $filing = app(CreateLiquorTaxMonthlyFilingDraftService::class)->create(2026, 6);
        $filing->update(['status' => 'confirmed']);

        $this->expectException(LiquorTaxMonthlyFilingDraftException::class);

        app(CreateLiquorTaxMonthlyFilingDraftService::class)->create(2026, 6);
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
            'customer_code' => 'LT-FILING-CUST-001',
            'name' => 'Liquor tax filing customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'LT-FILING-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Liquor tax filing sake',
            'display_name' => 'Liquor tax filing sake',
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
