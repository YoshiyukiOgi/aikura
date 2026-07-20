<?php

namespace Tests\Feature;

use App\Exceptions\Shipment\ShipmentConfirmationException;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\SettlementReceivableCategory;
use App\Models\StockLocation;
use App\Models\StockMonthlyBalance;
use App\Models\StockMovement;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Models\LiquorTaxCategory;
use App\Models\LiquorTaxRule;
use App\Services\Shipment\ApplyDraftShipmentPricingService;
use App\Services\Shipment\ConfirmShipmentService;
use App\Services\Shipment\CreateDraftShipmentData;
use App\Services\Shipment\CreateDraftShipmentLineData;
use App\Services\Shipment\CreateDraftShipmentService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\PriceMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Database\Seeders\TaxMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConfirmShipmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_snapshot_columns_exist(): void
    {
        foreach ([
            'confirmed_product_code',
            'confirmed_product_name',
            'confirmed_display_name',
            'confirmed_product_type',
            'confirmed_unit_code',
            'confirmed_unit_name',
            'confirmed_quantity',
            'confirmed_unit_price',
            'confirmed_price_list_id',
            'confirmed_price_rule_id',
            'confirmed_price_source',
            'confirmed_price_reason',
            'confirmed_capacity_value',
            'confirmed_capacity_unit_id',
            'confirmed_alcohol_percentage',
            'confirmed_rounding_method',
            'confirmed_consumption_tax_category_id',
            'confirmed_consumption_tax_category_code',
            'confirmed_consumption_tax_category_name',
            'confirmed_consumption_taxability',
            'confirmed_consumption_tax_rate_id',
            'confirmed_consumption_tax_rate',
            'confirmed_consumption_tax_rate_effective_from',
            'confirmed_liquor_tax_category_id',
            'confirmed_liquor_tax_category_code',
            'confirmed_liquor_tax_category_name',
            'confirmed_liquor_taxability',
            'confirmed_liquor_tax_rule_id',
            'confirmed_liquor_tax_calculation_method',
            'confirmed_liquor_taxable_kl',
            'confirmed_liquor_tax_per_kl',
            'confirmed_liquor_tax_reduction_rate',
            'confirmed_liquor_tax_estimated_amount',
            'confirmed_at',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('shipment_lines', $column), "Column [shipment_lines.{$column}] does not exist.");
        }
    }

    public function test_it_confirms_draft_shipment_and_stores_line_snapshot(): void
    {
        [$shipment, $product, $priceRule] = $this->preparePricedShipment();

        $confirmed = app(ConfirmShipmentService::class)->confirm(
            shipment: $shipment,
            reason: '内容確認済み',
        );

        $line = $confirmed->lines->first();

        $this->assertSame('confirmed', $confirmed->status);
        $this->assertSame($product->product_code, $line->confirmed_product_code);
        $this->assertSame('確定対象酒', $line->confirmed_product_name);
        $this->assertSame('確定対象酒 表示名', $line->confirmed_display_name);
        $this->assertSame('sake', $line->confirmed_product_type);
        $this->assertSame('bottle', $line->confirmed_unit_code);
        $this->assertSame('本', $line->confirmed_unit_name);
        $this->assertSame('3.0000', $line->confirmed_quantity);
        $this->assertSame('1500.0000', $line->confirmed_unit_price);
        $this->assertSame($priceRule->price_list_id, $line->confirmed_price_list_id);
        $this->assertSame($priceRule->id, $line->confirmed_price_rule_id);
        $this->assertSame('common', $line->confirmed_price_source);
        $this->assertSame('共通価格表', $line->confirmed_price_reason);
        $this->assertSame('720.0000', $line->confirmed_capacity_value);
        $this->assertSame('15.50', $line->confirmed_alcohol_percentage);
        $this->assertSame('round', $line->confirmed_rounding_method);
        $this->assertSame('taxable_standard', $line->confirmed_consumption_tax_category_code);
        $this->assertSame('taxable', $line->confirmed_consumption_taxability);
        $this->assertSame('0.1000', $line->confirmed_consumption_tax_rate);
        $this->assertSame('2019-10-01', $line->confirmed_consumption_tax_rate_effective_from->toDateString());
        $this->assertSame('seishu', $line->confirmed_liquor_tax_category_code);
        $this->assertSame('taxable', $line->confirmed_liquor_taxability);
        $this->assertSame('fixed_per_kl', $line->confirmed_liquor_tax_calculation_method);
        $this->assertSame('0.002160', $line->confirmed_liquor_taxable_kl);
        $this->assertSame('100000.0000', $line->confirmed_liquor_tax_per_kl);
        $this->assertSame('0.0000', $line->confirmed_liquor_tax_reduction_rate);
        $this->assertSame('216.00', $line->confirmed_liquor_tax_estimated_amount);
        $this->assertNotNull($line->confirmed_at);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'shipment.confirmed',
            'target_table' => 'shipment_headers',
            'target_id' => (string) $confirmed->id,
            'reason' => '内容確認済み',
        ]);
    }

    public function test_it_creates_confirmed_stock_movement_when_shipment_is_confirmed(): void
    {
        [$shipment] = $this->preparePricedShipment();

        $confirmed = app(ConfirmShipmentService::class)->confirm($shipment);
        $line = $confirmed->lines->first();
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();

        $this->assertDatabaseHas('stock_movements', [
            'status' => 'confirmed',
            'movement_type' => 'shipment',
            'movement_date' => '2026-05-23',
            'product_id' => $line->product_id,
            'stock_location_id' => $location->id,
            'unit_id' => $line->unit_id,
            'quantity' => '-3.0000',
            'source_type' => 'shipment',
            'source_document_number' => $confirmed->document_number,
            'source_line_no' => 1,
            'source_shipment_header_id' => $confirmed->id,
            'source_shipment_line_id' => $line->id,
        ]);

        $this->assertTrue($confirmed->stockMovements->first()->sourceShipmentLine->is($line));
    }

    public function test_it_does_not_create_stock_movement_for_non_inventory_managed_product(): void
    {
        [$shipment, $product] = $this->preparePricedShipment();
        $product->update(['is_inventory_managed' => false]);

        app(ConfirmShipmentService::class)->confirm($shipment);

        $this->assertSame(0, StockMovement::count());
    }

    public function test_it_rejects_shipment_confirmation_when_stock_period_is_confirmed(): void
    {
        [$shipment, $product] = $this->preparePricedShipment();
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();

        StockMonthlyBalance::create([
            'status' => 'confirmed',
            'year' => 2026,
            'month' => 5,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'unit_id' => $product->inventory_unit_id,
            'confirmed_at' => now(),
        ]);

        $this->expectException(\App\Exceptions\Inventory\ClosedStockPeriodException::class);

        app(ConfirmShipmentService::class)->confirm($shipment);
    }

    public function test_confirmed_snapshot_does_not_change_after_master_or_price_changes(): void
    {
        [$shipment, $product, $priceRule] = $this->preparePricedShipment();

        $confirmed = app(ConfirmShipmentService::class)->confirm($shipment);
        $line = $confirmed->lines->first();

        $product->update([
            'name' => '変更後の商品名',
            'display_name' => '変更後の表示名',
            'alcohol_percentage' => '16.00',
        ]);
        $priceRule->update(['unit_price' => '9999.0000']);

        $line->refresh();

        $this->assertSame('確定対象酒', $line->confirmed_product_name);
        $this->assertSame('確定対象酒 表示名', $line->confirmed_display_name);
        $this->assertSame('15.50', $line->confirmed_alcohol_percentage);
        $this->assertSame('1500.0000', $line->confirmed_unit_price);
    }

    public function test_it_rejects_confirmation_without_draft_price(): void
    {
        [$shipment] = $this->prepareUnpricedShipment();

        $this->expectException(ShipmentConfirmationException::class);

        app(ConfirmShipmentService::class)->confirm($shipment);
    }

    public function test_it_rejects_confirmation_for_non_draft_shipment(): void
    {
        [$shipment] = $this->preparePricedShipment();
        $shipment->update(['status' => 'confirmed']);

        $this->expectException(ShipmentConfirmationException::class);

        app(ConfirmShipmentService::class)->confirm($shipment);
    }

    public function test_liquor_tax_rule_uses_liquor_tax_transfer_date_when_present(): void
    {
        [$customer, $product, $unit] = $this->prepareBaseData();
        $seishu = LiquorTaxCategory::where('code', 'seishu')->firstOrFail();

        LiquorTaxRule::where('liquor_tax_category_id', $seishu->id)->update([
            'effective_to' => '2026-05-31',
        ]);
        $rule = LiquorTaxRule::create([
            'liquor_tax_category_id' => $seishu->id,
            'code' => 'seishu_transfer_date_test',
            'name' => 'Seishu transfer date test',
            'calculation_method' => 'fixed_per_kl',
            'tax_per_kl' => '200000.0000',
            'effective_from' => '2026-06-01',
            'effective_to' => null,
            'reduction_rate' => '0.0000',
            'is_active' => true,
        ]);

        $priceList = PriceList::where('code', 'common')->firstOrFail();
        PriceRule::create([
            'price_list_id' => $priceList->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'unit_price' => '1500.0000',
            'priority' => 300,
            'effective_from' => '2026-01-01',
        ]);

        $shipment = app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: '2026-05-31',
            liquorTaxTransferDate: '2026-06-01',
            lines: [
                new CreateDraftShipmentLineData($product->id, '3.0000', $unit->id),
            ],
        ));
        $shipment = app(ApplyDraftShipmentPricingService::class)->apply($shipment);

        $confirmed = app(ConfirmShipmentService::class)->confirm($shipment);
        $line = $confirmed->lines->first();

        $this->assertSame($rule->id, $line->confirmed_liquor_tax_rule_id);
        $this->assertSame('200000.0000', $line->confirmed_liquor_tax_per_kl);
        $this->assertSame('432.00', $line->confirmed_liquor_tax_estimated_amount);
    }

    /**
     * @return array{0: \App\Models\ShipmentHeader, 1: Product, 2: PriceRule}
     */
    private function preparePricedShipment(): array
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $priceList = PriceList::where('code', 'common')->firstOrFail();
        $priceRule = PriceRule::create([
            'price_list_id' => $priceList->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'unit_price' => '1500.0000',
            'priority' => 300,
            'effective_from' => '2026-01-01',
        ]);

        $shipment = $this->createDraft($customer, $product, $unit);
        $shipment = app(ApplyDraftShipmentPricingService::class)->apply($shipment);

        return [$shipment, $product, $priceRule];
    }

    /**
     * @return array{0: \App\Models\ShipmentHeader, 1: Product, 2: Unit}
     */
    private function prepareUnpricedShipment(): array
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        return [$this->createDraft($customer, $product, $unit), $product, $unit];
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
            'customer_code' => 'CONFIRM-CUST-001',
            'name' => '確定確認酒店',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'CONFIRM-SAKE-001',
            'product_type' => 'sake',
            'name' => '確定対象酒',
            'display_name' => '確定対象酒 表示名',
            'base_unit_id' => $bottle->id,
            'sales_unit_id' => $bottle->id,
            'inventory_unit_id' => $bottle->id,
            'capacity_value' => '720.0000',
            'capacity_unit_id' => $milliliter->id,
            'alcohol_percentage' => '15.50',
            'is_alcohol' => true,
        ]);

        return [$customer, $product, $bottle];
    }

    private function createDraft(Customer $customer, Product $product, Unit $unit): \App\Models\ShipmentHeader
    {
        return app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: '2026-05-23',
            lines: [
                new CreateDraftShipmentLineData($product->id, '3.0000', $unit->id),
            ],
        ));
    }
}
