<?php

namespace Tests\Feature;

use App\Exceptions\Shipment\ShipmentPickException;
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
use App\Services\ShipmentPicking\CancelShipmentPickService;
use App\Services\ShipmentPicking\PickShipmentInstructionData;
use App\Services\ShipmentPicking\PickShipmentInstructionLineData;
use App\Services\ShipmentPicking\PickShipmentInstructionService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Database\Seeders\StockLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelShipmentPickTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_cancels_pick_and_restores_instruction_picked_quantity(): void
    {
        [$pick, $instruction] = $this->preparePick('10.0000', '4.0000');

        $cancelled = app(CancelShipmentPickService::class)->cancel($pick, 'wrong pick');
        $instructionLine = $instruction->lines->first()->refresh();

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('wrong pick', $cancelled->cancelled_reason);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame('0.0000', $instructionLine->picked_quantity);
        $this->assertSame('instructed', $instruction->refresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'shipment_pick.cancelled',
            'target_table' => 'shipment_picks',
            'target_id' => (string) $pick->id,
            'reason' => 'wrong pick',
        ]);
    }

    public function test_it_recalculates_instruction_status_when_other_pick_remains(): void
    {
        [$firstPick, $instruction] = $this->preparePick('10.0000', '4.0000');
        $instructionLine = $instruction->lines->first();

        app(PickShipmentInstructionService::class)->pick(new PickShipmentInstructionData(
            shipmentInstructionId: $instruction->id,
            pickDate: '2026-06-22',
            lines: [
                new PickShipmentInstructionLineData($instructionLine->id, '2.0000'),
            ],
        ));

        app(CancelShipmentPickService::class)->cancel($firstPick, 'partial cancellation');

        $this->assertSame('2.0000', $instructionLine->refresh()->picked_quantity);
        $this->assertSame('partially_picked', $instruction->refresh()->status);
    }

    public function test_it_rejects_empty_reason(): void
    {
        [$pick] = $this->preparePick('10.0000', '4.0000');

        $this->expectException(ShipmentPickException::class);

        app(CancelShipmentPickService::class)->cancel($pick, '   ');
    }

    public function test_it_rejects_cancelling_already_cancelled_pick(): void
    {
        [$pick] = $this->preparePick('10.0000', '4.0000');
        $cancelled = app(CancelShipmentPickService::class)->cancel($pick, 'first cancellation');

        $this->expectException(ShipmentPickException::class);

        app(CancelShipmentPickService::class)->cancel($cancelled, 'second cancellation');
    }

    public function test_it_rejects_cancelling_pick_after_shipment_draft_was_created(): void
    {
        [$pick] = $this->preparePick('10.0000', '4.0000');

        app(CreateDraftShipmentFromPickService::class)->create(new CreateDraftShipmentFromPickData($pick->id));

        $this->expectException(ShipmentPickException::class);

        app(CancelShipmentPickService::class)->cancel($pick, 'already converted');
    }

    /**
     * @return array{0: ShipmentPick, 1: ShipmentInstruction}
     */
    private function preparePick(string $instructionQuantity, string $pickQuantity): array
    {
        $instruction = $this->prepareInstruction($instructionQuantity);
        $instructionLine = $instruction->lines->first();

        $pick = app(PickShipmentInstructionService::class)->pick(new PickShipmentInstructionData(
            shipmentInstructionId: $instruction->id,
            pickDate: '2026-06-22',
            lines: [
                new PickShipmentInstructionLineData($instructionLine->id, $pickQuantity),
            ],
        ));

        return [$pick, $instruction];
    }

    private function prepareInstruction(string $quantity): ShipmentInstruction
    {
        $this->seed([
            CustomerMasterSeeder::class,
            ProductUnitMasterSeeder::class,
            ShipmentMasterSeeder::class,
            StockLocationSeeder::class,
        ]);

        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();
        $unit = Unit::where('code', 'bottle')->firstOrFail();
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'CANCEL-PICK-CUST-001',
            'name' => 'Cancel pick customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'CANCEL-PICK-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Cancel pick sake',
            'display_name' => 'Cancel pick sake 720ml',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_alcohol' => true,
            'is_inventory_managed' => false,
        ]);

        $salesOrder = app(CreateSalesOrderService::class)->create(new CreateSalesOrderData(
            customerId: $customer->id,
            orderDate: '2026-06-20',
            lines: [
                new CreateSalesOrderLineData($product->id, $quantity, $unit->id),
            ],
        ));

        return app(CreateShipmentInstructionService::class)->create(new CreateShipmentInstructionData(
            instructionDate: '2026-06-21',
            stockLocationId: $location->id,
            lines: [
                new CreateShipmentInstructionLineData($salesOrder->lines->first()->id, $quantity),
            ],
        ));
    }
}
