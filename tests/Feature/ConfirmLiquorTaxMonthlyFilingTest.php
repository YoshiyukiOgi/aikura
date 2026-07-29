<?php

namespace Tests\Feature;

use App\Exceptions\Tax\ClosedLiquorTaxFilingPeriodException;
use App\Exceptions\Tax\LiquorTaxMonthlyFilingConfirmationException;
use App\Exceptions\Tax\LiquorTaxMonthlyFilingReopenException;
use App\Models\AuditLog;
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
use App\Services\Shipment\CancelShipmentService;
use App\Services\Shipment\ConfirmShipmentService;
use App\Services\Shipment\CreateDraftShipmentData;
use App\Services\Shipment\CreateDraftShipmentLineData;
use App\Services\Shipment\CreateDraftShipmentService;
use App\Services\Tax\ConfirmLiquorTaxMonthlyFilingService;
use App\Services\Tax\CreateLiquorTaxMonthlyFilingDraftService;
use App\Services\Tax\ReopenLiquorTaxMonthlyFilingService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\PriceMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Database\Seeders\TaxMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfirmLiquorTaxMonthlyFilingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_confirms_monthly_filing_and_freezes_confirmed_amounts(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $this->confirmShipment($customer, $product, $unit, '2026-06-01', '2026-06-01', '3.0000');
        app(CreateLiquorTaxMonthlyFilingDraftService::class)->create(2026, 6);

        $filing = app(ConfirmLiquorTaxMonthlyFilingService::class)
            ->confirm(2026, 6, 'monthly liquor tax filing confirmation');

        $this->assertSame('confirmed', $filing->status);
        $this->assertSame('216.00', $filing->total_gross_tax_amount);
        $this->assertSame('43.00', $filing->total_relief_amount);
        $this->assertSame('100.00', $filing->total_estimated_amount);
        $this->assertSame('100.00', $filing->total_confirmed_amount);
        $this->assertNotNull($filing->confirmed_at);
        $this->assertSame('monthly liquor tax filing confirmation', $filing->reason);

        $line = $filing->lines->first();
        $this->assertSame('173.00', $line->estimated_amount);
        $this->assertSame('173.00', $line->confirmed_amount);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'liquor_tax_monthly_filing.confirmed',
            'target_table' => 'liquor_tax_monthly_filings',
            'target_id' => (string) $filing->id,
            'reason' => 'monthly liquor tax filing confirmation',
        ]);
    }

    public function test_it_rejects_confirmation_without_draft(): void
    {
        $this->expectException(LiquorTaxMonthlyFilingConfirmationException::class);

        app(ConfirmLiquorTaxMonthlyFilingService::class)
            ->confirm(2026, 6, 'monthly liquor tax filing confirmation');
    }

    public function test_it_rejects_empty_reason(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $this->confirmShipment($customer, $product, $unit, '2026-06-01', '2026-06-01', '3.0000');
        app(CreateLiquorTaxMonthlyFilingDraftService::class)->create(2026, 6);

        $this->expectException(LiquorTaxMonthlyFilingConfirmationException::class);

        app(ConfirmLiquorTaxMonthlyFilingService::class)->confirm(2026, 6, ' ');
    }

    public function test_it_rejects_already_confirmed_filing(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $this->confirmShipment($customer, $product, $unit, '2026-06-01', '2026-06-01', '3.0000');
        app(CreateLiquorTaxMonthlyFilingDraftService::class)->create(2026, 6);
        app(ConfirmLiquorTaxMonthlyFilingService::class)->confirm(2026, 6, 'first confirmation');

        $this->expectException(LiquorTaxMonthlyFilingConfirmationException::class);

        app(ConfirmLiquorTaxMonthlyFilingService::class)->confirm(2026, 6, 'second confirmation');
    }

    public function test_it_reopens_latest_confirmed_filing_and_preserves_snapshot_in_audit_log(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $this->confirmShipment($customer, $product, $unit, '2026-06-01', '2026-06-01', '3.0000');
        $draft = app(CreateLiquorTaxMonthlyFilingDraftService::class)->create(2026, 6);
        $confirmed = app(ConfirmLiquorTaxMonthlyFilingService::class)->confirm(2026, 6, 'first confirmation');

        $reopened = app(ReopenLiquorTaxMonthlyFilingService::class)
            ->reopen($confirmed, 'shipment correction required');

        $this->assertSame($draft->id, $reopened->id);
        $this->assertSame('draft', $reopened->status);
        $this->assertNull($reopened->confirmed_at);
        $this->assertNull($reopened->total_confirmed_amount);
        $this->assertSame('shipment correction required', $reopened->reason);
        $this->assertNull($reopened->lines->first()->confirmed_amount);

        $audit = $this->assertDatabaseHas('audit_logs', [
            'event' => 'liquor_tax_monthly_filing.reopened',
            'target_table' => 'liquor_tax_monthly_filings',
            'target_id' => (string) $reopened->id,
            'reason' => 'shipment correction required',
        ]);
        $this->assertNotNull($audit);

        $snapshot = AuditLog::query()
            ->where('event', 'liquor_tax_monthly_filing.reopened')
            ->where('target_id', (string) $reopened->id)
            ->firstOrFail()
            ->before_values;
        $this->assertSame('confirmed', $snapshot['status']);
        $this->assertSame('100.00', $snapshot['total_confirmed_amount']);
        $this->assertSame('173.00', $snapshot['lines'][0]['confirmed_amount']);
    }

    public function test_it_rejects_reopening_when_a_newer_filing_is_confirmed(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $this->confirmShipment($customer, $product, $unit, '2026-05-01', '2026-05-01', '1.0000');
        $may = app(CreateLiquorTaxMonthlyFilingDraftService::class)->create(2026, 5);
        $may = app(ConfirmLiquorTaxMonthlyFilingService::class)->confirm(2026, 5, 'May confirmation');

        $this->confirmShipment($customer, $product, $unit, '2026-06-01', '2026-06-01', '1.0000');
        app(CreateLiquorTaxMonthlyFilingDraftService::class)->create(2026, 6);
        app(ConfirmLiquorTaxMonthlyFilingService::class)->confirm(2026, 6, 'June confirmation');

        $this->expectException(LiquorTaxMonthlyFilingReopenException::class);

        app(ReopenLiquorTaxMonthlyFilingService::class)->reopen($may, 'May correction');
    }

    public function test_it_rejects_shipment_confirmation_in_confirmed_liquor_tax_period(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $this->confirmShipment($customer, $product, $unit, '2026-06-01', '2026-06-01', '3.0000');
        app(CreateLiquorTaxMonthlyFilingDraftService::class)->create(2026, 6);
        app(ConfirmLiquorTaxMonthlyFilingService::class)->confirm(2026, 6, 'monthly liquor tax close');

        $shipment = $this->createPricedDraft($customer, $product, $unit, '2026-06-20', '2026-06-20', '1.0000');

        $this->expectException(ClosedLiquorTaxFilingPeriodException::class);

        app(ConfirmShipmentService::class)->confirm($shipment);
    }

    public function test_it_rejects_confirmed_shipment_cancellation_in_confirmed_liquor_tax_period(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $shipment = $this->confirmShipment($customer, $product, $unit, '2026-06-01', '2026-06-01', '3.0000');
        app(CreateLiquorTaxMonthlyFilingDraftService::class)->create(2026, 6);
        app(ConfirmLiquorTaxMonthlyFilingService::class)->confirm(2026, 6, 'monthly liquor tax close');

        $this->expectException(ClosedLiquorTaxFilingPeriodException::class);

        app(CancelShipmentService::class)->cancel($shipment, 'cancel after liquor tax close');
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
            'customer_code' => 'LT-CONFIRM-CUST-001',
            'name' => 'Liquor tax confirmation customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'LT-CONFIRM-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Liquor tax confirmation sake',
            'display_name' => 'Liquor tax confirmation sake',
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
        $shipment = $this->createPricedDraft($customer, $product, $unit, $documentDate, $liquorTaxTransferDate, $quantity);

        return app(ConfirmShipmentService::class)->confirm($shipment);
    }

    private function createPricedDraft(
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

        return app(ApplyDraftShipmentPricingService::class)->apply($shipment);
    }
}
