<?php

namespace Tests\Feature;

use App\Exceptions\Shipment\ShipmentDraftException;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SettlementReceivableCategory;
use App\Models\ShipmentInstruction;
use App\Models\ShipmentPick;
use App\Models\StockLocation;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Services\SalesOrder\CreateSalesOrderData;
use App\Services\SalesOrder\CreateSalesOrderLineData;
use App\Services\SalesOrder\CreateSalesOrderService;
use App\Services\Shipment\CreateDraftShipmentFromPickData;
use App\Services\Shipment\CreateDraftShipmentFromPickService;
use App\Services\ShipmentInstruction\CreateShipmentInstructionData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionLineData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionService;
use App\Services\ShipmentPicking\PickShipmentInstructionData;
use App\Services\ShipmentPicking\PickShipmentInstructionLineData;
use App\Services\ShipmentPicking\PickShipmentInstructionService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Database\Seeders\StockLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CreateDraftShipmentFromPickTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipment_tables_have_pick_source_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('shipment_headers', 'source_shipment_pick_id'));
        $this->assertTrue(Schema::hasColumn('shipment_lines', 'source_shipment_pick_line_id'));
    }

    public function test_it_creates_draft_shipment_from_pick(): void
    {
        $pick = $this->preparePick('8.0000', '3.0000');

        $shipment = app(CreateDraftShipmentFromPickService::class)->create(new CreateDraftShipmentFromPickData(
            shipmentPickId: $pick->id,
            documentDate: '2026-06-23',
            billingTargetDate: '2026-06-30',
            reason: 'create shipment from pick',
        ));

        $pickLine = $pick->lines->first();
        $shipmentLine = $shipment->lines->first();

        $this->assertSame('draft', $shipment->status);
        $this->assertStringStartsWith('S-', $shipment->document_number);
        $this->assertSame($pick->id, $shipment->source_shipment_pick_id);
        $this->assertSame('2026-06-23', $shipment->document_date->toDateString());
        $this->assertSame('2026-06-30', $shipment->billing_target_date->toDateString());
        $this->assertCount(1, $shipment->lines);
        $this->assertSame($pickLine->id, $shipmentLine->source_shipment_pick_line_id);
        $this->assertSame($pickLine->product_id, $shipmentLine->product_id);
        $this->assertSame('3.0000', $shipmentLine->quantity);
        $this->assertSame($pickLine->unit_id, $shipmentLine->unit_id);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'shipment.draft_created_from_pick',
            'target_table' => 'shipment_headers',
            'target_id' => (string) $shipment->id,
            'reason' => 'create shipment from pick',
        ]);
    }

    public function test_it_rejects_duplicate_conversion_from_same_pick(): void
    {
        $pick = $this->preparePick('8.0000', '3.0000');
        $service = app(CreateDraftShipmentFromPickService::class);

        $service->create(new CreateDraftShipmentFromPickData($pick->id));

        $this->expectException(ShipmentDraftException::class);

        $service->create(new CreateDraftShipmentFromPickData($pick->id));
    }

    public function test_it_rejects_cancelled_pick(): void
    {
        $pick = $this->preparePick('8.0000', '3.0000');
        $pick->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_reason' => 'test',
        ])->save();

        $this->expectException(ShipmentDraftException::class);

        app(CreateDraftShipmentFromPickService::class)->create(new CreateDraftShipmentFromPickData($pick->id));
    }

    public function test_it_preserves_export_settlement_receivable_category_from_sales_order(): void
    {
        $pick = $this->preparePick('8.0000', '3.0000', 'export');
        $exportCategoryId = SettlementReceivableCategory::where('code', 'export')->value('id');

        $shipment = app(CreateDraftShipmentFromPickService::class)->create(new CreateDraftShipmentFromPickData(
            shipmentPickId: $pick->id,
            documentDate: '2026-06-23',
        ));

        $this->assertSame($exportCategoryId, $shipment->settlement_receivable_category_id);
    }

    private function preparePick(string $instructionQuantity, string $pickQuantity, string $settlementCategoryCode = 'accounts_receivable_1'): ShipmentPick
    {
        $instruction = $this->prepareInstruction($instructionQuantity, $settlementCategoryCode);
        $instructionLine = $instruction->lines->first();

        return app(PickShipmentInstructionService::class)->pick(new PickShipmentInstructionData(
            shipmentInstructionId: $instruction->id,
            pickDate: '2026-06-22',
            lines: [
                new PickShipmentInstructionLineData($instructionLine->id, $pickQuantity, 'picked line'),
            ],
        ));
    }

    private function prepareInstruction(string $quantity, string $settlementCategoryCode = 'accounts_receivable_1'): ShipmentInstruction
    {
        $this->seed([
            CustomerMasterSeeder::class,
            ProductUnitMasterSeeder::class,
            ShipmentMasterSeeder::class,
            StockLocationSeeder::class,
        ]);

        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail();
        $orderSettlementCategory = SettlementReceivableCategory::where('code', $settlementCategoryCode)->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();
        $unit = Unit::where('code', 'bottle')->firstOrFail();
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'PICK-SHIP-CUST-001',
            'name' => 'Pick shipment customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'PICK-SHIP-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Pick shipment sake',
            'display_name' => 'Pick shipment sake 720ml',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_alcohol' => true,
        ]);

        $salesOrder = app(CreateSalesOrderService::class)->create(new CreateSalesOrderData(
            customerId: $customer->id,
            orderDate: '2026-06-20',
            settlementReceivableCategoryId: $orderSettlementCategory->id,
            lines: [
                new CreateSalesOrderLineData($product->id, $quantity, $unit->id),
            ],
        ));

        return app(CreateShipmentInstructionService::class)->create(new CreateShipmentInstructionData(
            instructionDate: '2026-06-21',
            scheduledShipmentDate: '2026-06-23',
            stockLocationId: $location->id,
            lines: [
                new CreateShipmentInstructionLineData($salesOrder->lines->first()->id, $quantity),
            ],
        ));
    }
}
