<?php

namespace Tests\Feature;

use App\Exceptions\Billing\SalesReturnException;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\InvoiceHeader;
use App\Models\PriceList;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\SettlementReceivableCategory;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Services\Billing\ConfirmInvoiceService;
use App\Services\Billing\CreateInvoiceDraftData;
use App\Services\Billing\CreateInvoiceDraftService;
use App\Services\Billing\CreateSalesReturnData;
use App\Services\Billing\CreateSalesReturnLineData;
use App\Services\Billing\CreateSalesReturnLineLotData;
use App\Services\Billing\CreateSalesReturnService;
use App\Services\Shipment\AllocateShipmentLineLotService;
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

class SalesReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_sales_return_credit_memo_and_return_stock_movement(): void
    {
        [$customer, $product, $unit, $location] = $this->prepareBaseData();
        $invoice = $this->createConfirmedInvoice($customer, $product, $unit);
        $sourceLine = $invoice->lines->first();

        $return = app(CreateSalesReturnService::class)->create(new CreateSalesReturnData(
            customerId: $customer->id,
            returnDate: '2026-06-10',
            reason: 'retail store return',
            lines: [
                new CreateSalesReturnLineData(
                    sourceInvoiceLineId: $sourceLine->id,
                    quantity: '1.0000',
                    stockAction: 'return_dedicated_stock',
                    stockLocationId: $location->id,
                    reason: 'unopened return',
                ),
            ],
        ));

        $returnLine = $return->lines->first();
        $creditInvoice = $return->creditInvoiceHeader;
        $creditLine = $creditInvoice->lines->first();

        $this->assertSame('credit_drafted', $return->status);
        $this->assertStringStartsWith('R-', $return->return_number);
        $this->assertSame('1500.00', $returnLine->amount);
        $this->assertSame('0.1000', $returnLine->tax_rate);
        $this->assertSame('150.00', $returnLine->tax_amount);
        $this->assertNotNull($returnLine->stock_movement_id);

        $this->assertSame('credit_memo', $creditInvoice->document_type);
        $this->assertStringStartsWith('C-', $creditInvoice->invoice_number);
        $this->assertSame('-1500.00', $creditInvoice->subtotal_amount);
        $this->assertSame('-150.00', $creditInvoice->tax_amount);
        $this->assertSame('-1650.00', $creditInvoice->total_amount);
        $this->assertSame('-1.0000', $creditLine->quantity);
        $this->assertSame($sourceLine->id, $creditLine->source_invoice_line_id);
        $this->assertSame($returnLine->id, $creditLine->source_sales_return_line_id);

        $this->assertSame('1.0000', $this->currentStockQuantity($location, $unit));

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'sales_return.credit_drafted',
            'target_table' => 'sales_return_headers',
            'target_id' => (string) $return->id,
            'reason' => 'retail store return',
        ]);
    }

    public function test_it_creates_credit_memo_without_stock_movement_for_no_stock_return(): void
    {
        [$customer, $product, $unit, $location] = $this->prepareBaseData();
        $invoice = $this->createConfirmedInvoice($customer, $product, $unit);
        $sourceLine = $invoice->lines->first();

        $return = app(CreateSalesReturnService::class)->create(new CreateSalesReturnData(
            customerId: $customer->id,
            returnDate: '2026-06-10',
            reason: 'credit only return',
            lines: [
                new CreateSalesReturnLineData(
                    sourceInvoiceLineId: $sourceLine->id,
                    quantity: '1.0000',
                    stockAction: 'no_stock',
                    reason: 'disposed by customer',
                ),
            ],
        ));

        $returnLine = $return->lines->first();
        $creditInvoice = $return->creditInvoiceHeader;
        $creditLine = $creditInvoice->lines->first();

        $this->assertSame('credit_drafted', $return->status);
        $this->assertSame('no_stock', $returnLine->stock_action);
        $this->assertNull($returnLine->stock_location_id);
        $this->assertNull($returnLine->stock_movement_id);
        $this->assertSame('-1650.00', $creditInvoice->total_amount);
        $this->assertSame('-1.0000', $creditLine->quantity);
        $this->assertSame($returnLine->id, $creditLine->source_sales_return_line_id);

        $this->assertSame('0.0000', $this->currentStockQuantity($location, $unit));
        $this->assertDatabaseMissing('stock_movements', [
            'source_document_number' => $return->return_number,
            'movement_type' => 'sales_return',
        ]);
    }

    public function test_regular_sales_stock_return_requires_source_lot(): void
    {
        [$customer, $product, $unit, $location] = $this->prepareBaseData();
        $invoice = $this->createConfirmedInvoice($customer, $product, $unit);
        $sourceLine = $invoice->lines->first();

        $this->expectException(SalesReturnException::class);

        app(CreateSalesReturnService::class)->create(new CreateSalesReturnData(
            customerId: $customer->id,
            returnDate: '2026-06-10',
            reason: 'regular stock return without lot',
            lines: [
                new CreateSalesReturnLineData(
                    sourceInvoiceLineId: $sourceLine->id,
                    quantity: '1.0000',
                    stockAction: 'return_stock',
                    stockLocationId: $location->id,
                ),
            ],
        ));
    }

    public function test_regular_sales_stock_return_can_restore_to_source_lot(): void
    {
        [$customer, $product, $unit, $location] = $this->prepareBaseData();
        $invoice = $this->createConfirmedInvoice($customer, $product, $unit);
        $sourceLine = $invoice->lines->first();
        $sourceLotId = $sourceLine->shipmentLine->lotAllocations()->firstOrFail()->production_lot_id;

        $return = app(CreateSalesReturnService::class)->create(new CreateSalesReturnData(
            customerId: $customer->id,
            returnDate: '2026-06-10',
            reason: 'regular stock return with source lot',
            lines: [
                new CreateSalesReturnLineData(
                    sourceInvoiceLineId: $sourceLine->id,
                    quantity: '1.0000',
                    stockAction: 'return_stock',
                    stockLocationId: $location->id,
                    productionLotId: $sourceLotId,
                ),
            ],
        ));

        $movement = StockMovement::query()->findOrFail($return->lines->first()->stock_movement_id);

        $this->assertSame($sourceLotId, $movement->production_lot_id);
        $this->assertSame('sales_return', $movement->movement_type);
        $this->assertSame('1.0000', $movement->quantity);
    }

    public function test_regular_sales_stock_return_can_split_quantity_across_source_lots(): void
    {
        [$customer, $product, $unit, $location] = $this->prepareBaseData();
        $invoice = $this->createConfirmedInvoice($customer, $product, $unit);
        $sourceLine = $invoice->lines->first();
        $firstAllocation = $sourceLine->shipmentLine->lotAllocations()->firstOrFail();
        $secondLot = ProductionLot::create([
            'lot_code' => 'RETURN-SOURCE-LOT-002',
            'display_name' => '返品元ロット002',
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'production_date' => '2026-05-02',
            'alcohol_percentage' => '15.50',
            'analysis_status' => 'confirmed',
        ]);

        $firstAllocation->update(['quantity' => '1.0000']);
        $sourceLine->shipmentLine->lotAllocations()->create([
            'status' => 'confirmed',
            'shipment_header_id' => $sourceLine->shipment_header_id,
            'product_id' => $product->id,
            'production_lot_id' => $secondLot->id,
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'quantity' => '1.0000',
            'allocated_at' => now(),
            'confirmed_at' => now(),
            'reason' => 'split lot test',
        ]);

        $return = app(CreateSalesReturnService::class)->create(new CreateSalesReturnData(
            customerId: $customer->id,
            returnDate: '2026-06-10',
            reason: 'regular stock return split lots',
            lines: [
                new CreateSalesReturnLineData(
                    sourceInvoiceLineId: $sourceLine->id,
                    quantity: '2.0000',
                    stockAction: 'return_stock',
                    stockLocationId: $location->id,
                    lots: [
                        new CreateSalesReturnLineLotData($firstAllocation->production_lot_id, '1.0000', $location->id),
                        new CreateSalesReturnLineLotData($secondLot->id, '1.0000', $location->id),
                    ],
                ),
            ],
        ));

        $returnLine = $return->lines->first();

        $this->assertCount(2, $returnLine->lots);
        $this->assertDatabaseHas('stock_movements', [
            'source_document_number' => $return->return_number,
            'production_lot_id' => $firstAllocation->production_lot_id,
            'quantity' => '1.0000',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'source_document_number' => $return->return_number,
            'production_lot_id' => $secondLot->id,
            'quantity' => '1.0000',
        ]);
    }

    /**
     * @return array{0: Customer, 1: Product, 2: Unit, 3: StockLocation}
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
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'RETURN-CUST-001',
            'name' => '返品確認小売店',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'RETURN-SAKE-001',
            'product_type' => 'sake',
            'name' => '返品確認酒',
            'display_name' => '返品確認酒 720ml',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'alcohol_percentage' => '15.50',
            'is_alcohol' => true,
            'is_inventory_managed' => true,
        ]);

        PriceRule::create([
            'price_list_id' => PriceList::where('code', 'common')->firstOrFail()->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'unit_price' => '1500.0000',
            'priority' => 300,
            'effective_from' => '2026-01-01',
        ]);

        return [$customer, $product, $unit, $location];
    }

    private function createConfirmedInvoice(Customer $customer, Product $product, Unit $unit): InvoiceHeader
    {
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();
        $lot = ProductionLot::create([
            'lot_code' => 'RETURN-SOURCE-LOT-001',
            'display_name' => '返品元ロット001',
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'production_date' => '2026-05-01',
            'alcohol_percentage' => '15.50',
            'analysis_status' => 'confirmed',
        ]);

        StockMovement::create([
            'status' => 'confirmed',
            'movement_type' => 'inventory_adjustment',
            'movement_date' => '2026-05-01',
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'quantity' => '2.0000',
            'production_lot_id' => $lot->id,
            'lot_code' => $lot->lot_code,
            'confirmed_at' => now(),
            'reason' => 'test opening stock',
        ]);

        $shipment = app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: '2026-06-01',
            billingTargetDate: '2026-06-01',
            lines: [
                new CreateDraftShipmentLineData($product->id, '2.0000', $unit->id),
            ],
        ));

        $shipment = app(ApplyDraftShipmentPricingService::class)->apply($shipment);
        app(AllocateShipmentLineLotService::class)->allocate(
            shipmentLine: $shipment->lines->first(),
            productionLot: $lot,
            stockLocation: $location,
            quantity: '2.0000',
            reason: 'test allocation',
        );
        $shipment = app(ConfirmShipmentService::class)->confirm($shipment);

        $invoice = app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: '2026-06-05',
            shipmentHeaderIds: [$shipment->id],
        ));

        return app(ConfirmInvoiceService::class)->confirm($invoice, 'source invoice confirm');
    }

    private function currentStockQuantity(StockLocation $location, Unit $unit): string
    {
        return bcadd((string) StockMovement::query()
            ->where('stock_location_id', $location->id)
            ->where('unit_id', $unit->id)
            ->whereIn('status', ['confirmed', 'closed'])
            ->whereNull('cancelled_at')
            ->sum('quantity'), '0', 4);
    }
}
