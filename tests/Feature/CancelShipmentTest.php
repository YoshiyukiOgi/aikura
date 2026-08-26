<?php

namespace Tests\Feature;

use App\Exceptions\Shipment\ShipmentCancellationException;
use App\Exceptions\StateMachine\InvalidStatusTransitionException;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\SettlementReceivableCategory;
use App\Models\StockLocation;
use App\Models\StockLotMonthlyBalance;
use App\Models\StockMovement;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Services\Shipment\ApplyDraftShipmentPricingService;
use App\Services\Shipment\CancelShipmentService;
use App\Services\Shipment\ConfirmShipmentService;
use App\Services\Shipment\CreateDraftShipmentData;
use App\Services\Shipment\CreateDraftShipmentLineData;
use App\Services\Shipment\CreateDraftShipmentService;
use App\Services\Inventory\CurrentStockBalanceService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\PriceMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelShipmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_cancels_draft_shipment_with_reason_and_audit_log(): void
    {
        [$shipment] = $this->prepareDraftShipment();

        $cancelled = app(CancelShipmentService::class)->cancel($shipment, '入力誤り');

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('入力誤り', $cancelled->cancelled_reason);
        $this->assertNotNull($cancelled->cancelled_at);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'shipment.cancelled',
            'target_table' => 'shipment_headers',
            'target_id' => (string) $shipment->id,
            'reason' => '入力誤り',
        ]);
    }

    public function test_it_cancels_confirmed_shipment_without_destroying_snapshot(): void
    {
        [$shipment, $product] = $this->prepareConfirmedShipment();

        $cancelled = app(CancelShipmentService::class)->cancel($shipment, '先方都合により取消');
        $line = $cancelled->lines->first();

        $product->update([
            'name' => '取消後の商品名変更',
            'display_name' => '取消後の表示名変更',
        ]);

        $line->refresh();

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('先方都合により取消', $cancelled->cancelled_reason);
        $this->assertSame('取消確認酒', $line->confirmed_product_name);
        $this->assertSame('取消確認酒 表示名', $line->confirmed_display_name);
        $this->assertSame('1800.0000', $line->confirmed_unit_price);
    }

    public function test_it_reverses_shipment_stock_movement_when_confirmed_shipment_is_cancelled(): void
    {
        [$shipment, , $unit] = $this->prepareConfirmedShipment();
        $line = $shipment->lines->first();
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();
        $originalMovement = StockMovement::where('source_shipment_header_id', $shipment->id)
            ->where('movement_type', 'shipment')
            ->firstOrFail();

        app(CancelShipmentService::class)->cancel($shipment, 'stock return by cancellation');

        $this->assertDatabaseHas('stock_movements', [
            'status' => 'confirmed',
            'movement_type' => 'shipment_cancellation',
            'movement_date' => '2026-07-23',
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'quantity' => '2.0000',
            'source_type' => 'shipment_cancellation',
            'source_document_number' => $shipment->document_number,
            'related_stock_movement_id' => $originalMovement->id,
            'reason' => 'stock return by cancellation',
        ]);

        $balance = app(CurrentStockBalanceService::class)
            ->forProductLocationUnit($line->product_id, $location->id, $unit->id);

        $this->assertSame('0.0000', $balance->physicalQuantity);
    }

    public function test_it_rejects_cancelling_confirmed_shipment_when_stock_period_is_confirmed(): void
    {
        [$shipment, $product, $unit] = $this->prepareConfirmedShipment();
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();
        $lot = ProductionLot::create([
            'lot_code' => 'CANCEL-SH-LOT-001',
            'display_name' => 'Cancel shipment lot',
            'status' => 'active',
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);

        StockLotMonthlyBalance::create([
            'status' => 'confirmed',
            'year' => 2026,
            'month' => 7,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'production_lot_id' => $lot->id,
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'closing_quantity' => '0.0000',
            'confirmed_at' => now(),
        ]);

        $this->expectException(\App\Exceptions\Inventory\ClosedStockPeriodException::class);

        app(CancelShipmentService::class)->cancel($shipment, 'closed stock period');
    }

    public function test_it_rejects_empty_reason(): void
    {
        [$shipment] = $this->prepareDraftShipment();

        $this->expectException(ShipmentCancellationException::class);

        app(CancelShipmentService::class)->cancel($shipment, '   ');
    }

    public function test_it_rejects_cancelling_already_cancelled_shipment(): void
    {
        [$shipment] = $this->prepareDraftShipment();

        $cancelled = app(CancelShipmentService::class)->cancel($shipment, '初回取消');

        $this->expectException(InvalidStatusTransitionException::class);

        app(CancelShipmentService::class)->cancel($cancelled, '二重取消');
    }

    public function test_it_rejects_cancelling_closed_shipment(): void
    {
        [$shipment] = $this->prepareDraftShipment();
        $shipment->update(['status' => 'closed']);

        $this->expectException(InvalidStatusTransitionException::class);

        app(CancelShipmentService::class)->cancel($shipment, '締め済み取消不可');
    }

    /**
     * @return array{0: \App\Models\ShipmentHeader, 1: Product, 2: Unit}
     */
    private function prepareDraftShipment(): array
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $shipment = app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: '2026-07-23',
            lines: [
                new CreateDraftShipmentLineData($product->id, '1.0000', $unit->id),
            ],
        ));

        return [$shipment, $product, $unit];
    }

    /**
     * @return array{0: \App\Models\ShipmentHeader, 1: Product, 2: Unit}
     */
    private function prepareConfirmedShipment(): array
    {
        [$customer, $product, $unit] = $this->prepareBaseData();

        $priceList = PriceList::where('code', 'common')->firstOrFail();
        PriceRule::create([
            'price_list_id' => $priceList->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'unit_price' => '1800.0000',
            'priority' => 300,
            'effective_from' => '2026-01-01',
        ]);

        $shipment = app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: '2026-07-23',
            lines: [
                new CreateDraftShipmentLineData($product->id, '2.0000', $unit->id),
            ],
        ));

        $shipment = app(ApplyDraftShipmentPricingService::class)->apply($shipment);
        $shipment = app(ConfirmShipmentService::class)->confirm($shipment);

        return [$shipment, $product, $unit];
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

        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();
        $unit = Unit::where('code', 'bottle')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'CANCEL-CUST-001',
            'name' => '取消確認酒店',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'CANCEL-SAKE-001',
            'product_type' => 'sake',
            'name' => '取消確認酒',
            'display_name' => '取消確認酒 表示名',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_alcohol' => true,
        ]);

        return [$customer, $product, $unit];
    }
}
