<?php

namespace Tests\Feature;

use App\Exceptions\Pricing\PriceResolutionException;
use App\Exceptions\Shipment\ShipmentDraftException;
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

class ApplyDraftShipmentPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipment_lines_have_draft_pricing_columns_but_no_confirmed_price_columns(): void
    {
        foreach ([
            'draft_unit_price',
            'draft_price_list_id',
            'draft_price_rule_id',
            'draft_price_source',
            'draft_price_reason',
            'draft_priced_at',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('shipment_lines', $column), "Column [shipment_lines.{$column}] does not exist.");
        }

        foreach (['unit_price', 'tax_amount', 'liquor_tax_amount'] as $column) {
            $this->assertFalse(Schema::hasColumn('shipment_lines', $column), "Confirmed snapshot column [shipment_lines.{$column}] should not exist yet.");
        }
    }

    public function test_it_applies_resolved_price_to_draft_lines(): void
    {
        [$customer, $product, $unit] = $this->prepareData();

        $priceList = PriceList::where('code', 'customer')->firstOrFail();
        $priceRule = $this->createRule($priceList, $product, $unit, '1200.0000', 100, [
            'customer_id' => $customer->id,
        ]);

        $shipment = $this->createDraftShipment($customer, $product, $unit);

        $pricedShipment = app(ApplyDraftShipmentPricingService::class)->apply(
            shipment: $shipment,
            reason: '価格確認',
        );

        $line = $pricedShipment->lines->first();

        $this->assertSame('draft', $pricedShipment->status);
        $this->assertSame('1200.0000', $line->draft_unit_price);
        $this->assertSame($priceList->id, $line->draft_price_list_id);
        $this->assertSame($priceRule->id, $line->draft_price_rule_id);
        $this->assertSame('customer', $line->draft_price_source);
        $this->assertSame('取引先個別価格', $line->draft_price_reason);
        $this->assertNotNull($line->draft_priced_at);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'shipment.draft_priced',
            'target_table' => 'shipment_headers',
            'target_id' => (string) $shipment->id,
            'reason' => '価格確認',
        ]);
    }

    public function test_it_uses_document_date_for_effective_price(): void
    {
        [$customer, $product, $unit] = $this->prepareData();

        $priceList = PriceList::where('code', 'common')->firstOrFail();
        $this->createRule($priceList, $product, $unit, '1000.0000', 300, [
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-04-30',
        ]);
        $this->createRule($priceList, $product, $unit, '1100.0000', 300, [
            'effective_from' => '2026-05-01',
        ]);

        $shipment = $this->createDraftShipment($customer, $product, $unit, '2026-05-23');

        $pricedShipment = app(ApplyDraftShipmentPricingService::class)->apply($shipment);

        $this->assertSame('1100.0000', $pricedShipment->lines->first()->draft_unit_price);
    }

    public function test_it_rejects_pricing_for_non_draft_shipment(): void
    {
        [$customer, $product, $unit] = $this->prepareData();

        $this->createRule(PriceList::where('code', 'common')->firstOrFail(), $product, $unit, '1100.0000', 300, []);
        $shipment = $this->createDraftShipment($customer, $product, $unit);
        $shipment->update(['status' => 'confirmed']);

        $this->expectException(ShipmentDraftException::class);

        app(ApplyDraftShipmentPricingService::class)->apply($shipment);
    }

    public function test_missing_price_throws_exception(): void
    {
        [$customer, $product, $unit] = $this->prepareData();
        $shipment = $this->createDraftShipment($customer, $product, $unit);

        $this->expectException(PriceResolutionException::class);

        app(ApplyDraftShipmentPricingService::class)->apply($shipment);
    }

    /**
     * @return array{0: Customer, 1: Product, 2: Unit}
     */
    private function prepareData(): array
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
            'customer_code' => 'PRICE-SHIP-CUST-001',
            'name' => '価格適用酒店',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'PRICE-SHIP-SAKE-001',
            'product_type' => 'sake',
            'name' => '価格適用酒',
            'display_name' => '価格適用酒',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_alcohol' => true,
        ]);

        return [$customer, $product, $unit];
    }

    private function createDraftShipment(Customer $customer, Product $product, Unit $unit, string $documentDate = '2026-05-23'): ShipmentHeader
    {
        return app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: $documentDate,
            lines: [
                new CreateDraftShipmentLineData($product->id, '2.0000', $unit->id),
            ],
        ));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createRule(PriceList $priceList, Product $product, Unit $unit, string $unitPrice, int $priority, array $overrides): PriceRule
    {
        return PriceRule::create(array_merge([
            'price_list_id' => $priceList->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'unit_price' => $unitPrice,
            'priority' => $priority,
            'effective_from' => '2026-01-01',
            'rounding_method' => 'round',
        ], $overrides));
    }
}
