<?php

namespace Tests\Feature;

use App\Exceptions\Shipment\ShipmentPickException;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SettlementReceivableCategory;
use App\Models\ShipmentInstruction;
use App\Models\StockLocation;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Services\SalesOrder\CreateSalesOrderData;
use App\Services\SalesOrder\CreateSalesOrderLineData;
use App\Services\SalesOrder\CreateSalesOrderService;
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

class PickShipmentInstructionTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipment_pick_tables_exist(): void
    {
        $this->assertTrue(Schema::hasColumn('shipment_instruction_lines', 'picked_quantity'));
        $this->assertTrue(Schema::hasTable('shipment_picks'));
        $this->assertTrue(Schema::hasTable('shipment_pick_lines'));

        foreach ([
            'pick_number',
            'status',
            'shipment_instruction_id',
            'pick_date',
            'stock_location_id',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('shipment_picks', $column), "Column [shipment_picks.{$column}] does not exist.");
        }

        foreach ([
            'shipment_pick_id',
            'line_no',
            'shipment_instruction_line_id',
            'product_id',
            'quantity',
            'unit_id',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('shipment_pick_lines', $column), "Column [shipment_pick_lines.{$column}] does not exist.");
        }
    }

    public function test_it_picks_shipment_instruction_line(): void
    {
        [$instruction, $location] = $this->prepareInstruction('10.0000');
        $instructionLine = $instruction->lines->first();

        $pick = app(PickShipmentInstructionService::class)->pick(new PickShipmentInstructionData(
            shipmentInstructionId: $instruction->id,
            pickDate: '2026-06-22',
            stockLocationId: $location->id,
            reason: 'pick test',
            lines: [
                new PickShipmentInstructionLineData($instructionLine->id, '4.0000', 'first pick'),
            ],
        ));

        $instructionLine->refresh();
        $instruction->refresh();

        $this->assertSame('picked', $pick->status);
        $this->assertStringStartsWith('P-', $pick->pick_number);
        $this->assertSame($instruction->id, $pick->shipment_instruction_id);
        $this->assertSame($location->id, $pick->stock_location_id);
        $this->assertCount(1, $pick->lines);
        $this->assertSame($instructionLine->id, $pick->lines->first()->shipment_instruction_line_id);
        $this->assertSame('4.0000', $pick->lines->first()->quantity);
        $this->assertSame('4.0000', $instructionLine->picked_quantity);
        $this->assertSame('partially_picked', $instruction->status);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'shipment_pick.created',
            'target_table' => 'shipment_picks',
            'target_id' => (string) $pick->id,
            'reason' => 'pick test',
        ]);
    }

    public function test_it_marks_instruction_picked_when_all_quantity_is_picked(): void
    {
        [$instruction] = $this->prepareInstruction('10.0000');
        $instructionLine = $instruction->lines->first();

        app(PickShipmentInstructionService::class)->pick(new PickShipmentInstructionData(
            shipmentInstructionId: $instruction->id,
            pickDate: '2026-06-22',
            lines: [
                new PickShipmentInstructionLineData($instructionLine->id, '10.0000'),
            ],
        ));

        $instructionLine->refresh();
        $instruction->refresh();

        $this->assertSame('10.0000', $instructionLine->picked_quantity);
        $this->assertSame('picked', $instruction->status);
    }

    public function test_it_allows_split_picking_up_to_instruction_quantity(): void
    {
        [$instruction] = $this->prepareInstruction('10.0000');
        $instructionLine = $instruction->lines->first();
        $service = app(PickShipmentInstructionService::class);

        $first = $service->pick(new PickShipmentInstructionData(
            shipmentInstructionId: $instruction->id,
            pickDate: '2026-06-22',
            lines: [
                new PickShipmentInstructionLineData($instructionLine->id, '4.0000'),
            ],
        ));
        $second = $service->pick(new PickShipmentInstructionData(
            shipmentInstructionId: $instruction->id,
            pickDate: '2026-06-22',
            lines: [
                new PickShipmentInstructionLineData($instructionLine->id, '6.0000'),
            ],
        ));

        $instructionLine->refresh();
        $instruction->refresh();

        $this->assertNotSame($first->pick_number, $second->pick_number);
        $this->assertSame('10.0000', $instructionLine->picked_quantity);
        $this->assertSame('picked', $instruction->status);
    }

    public function test_it_rejects_empty_lines(): void
    {
        [$instruction] = $this->prepareInstruction('10.0000');

        $this->expectException(ShipmentPickException::class);

        app(PickShipmentInstructionService::class)->pick(new PickShipmentInstructionData(
            shipmentInstructionId: $instruction->id,
            pickDate: '2026-06-22',
            lines: [],
        ));
    }

    public function test_it_rejects_non_positive_quantity(): void
    {
        [$instruction] = $this->prepareInstruction('10.0000');
        $instructionLine = $instruction->lines->first();

        $this->expectException(ShipmentPickException::class);

        app(PickShipmentInstructionService::class)->pick(new PickShipmentInstructionData(
            shipmentInstructionId: $instruction->id,
            pickDate: '2026-06-22',
            lines: [
                new PickShipmentInstructionLineData($instructionLine->id, '0.0000'),
            ],
        ));
    }

    public function test_it_rejects_quantity_over_remaining_pick_quantity(): void
    {
        [$instruction] = $this->prepareInstruction('3.0000');
        $instructionLine = $instruction->lines->first();

        $this->expectException(ShipmentPickException::class);

        app(PickShipmentInstructionService::class)->pick(new PickShipmentInstructionData(
            shipmentInstructionId: $instruction->id,
            pickDate: '2026-06-22',
            lines: [
                new PickShipmentInstructionLineData($instructionLine->id, '4.0000'),
            ],
        ));
    }

    public function test_it_rejects_duplicate_instruction_line_in_same_pick(): void
    {
        [$instruction] = $this->prepareInstruction('10.0000');
        $instructionLine = $instruction->lines->first();

        $this->expectException(ShipmentPickException::class);

        app(PickShipmentInstructionService::class)->pick(new PickShipmentInstructionData(
            shipmentInstructionId: $instruction->id,
            pickDate: '2026-06-22',
            lines: [
                new PickShipmentInstructionLineData($instructionLine->id, '1.0000'),
                new PickShipmentInstructionLineData($instructionLine->id, '1.0000'),
            ],
        ));
    }

    public function test_it_rejects_line_from_different_instruction(): void
    {
        [$firstInstruction] = $this->prepareInstruction('3.0000', 'PICK-CUST-001', 'PICK-SAKE-001');
        [$secondInstruction] = $this->prepareInstruction('3.0000', 'PICK-CUST-002', 'PICK-SAKE-002');

        $this->expectException(ShipmentPickException::class);

        app(PickShipmentInstructionService::class)->pick(new PickShipmentInstructionData(
            shipmentInstructionId: $firstInstruction->id,
            pickDate: '2026-06-22',
            lines: [
                new PickShipmentInstructionLineData($secondInstruction->lines->first()->id, '1.0000'),
            ],
        ));
    }

    /**
     * @return array{0: ShipmentInstruction, 1: StockLocation}
     */
    private function prepareInstruction(
        string $quantity,
        string $customerCode = 'PICK-CUST-001',
        string $productCode = 'PICK-SAKE-001',
    ): array {
        if (! Unit::where('code', 'bottle')->exists()) {
            $this->seed([
                CustomerMasterSeeder::class,
                ProductUnitMasterSeeder::class,
                ShipmentMasterSeeder::class,
                StockLocationSeeder::class,
            ]);
        }

        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();
        $unit = Unit::where('code', 'bottle')->firstOrFail();
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => $customerCode,
            'name' => $customerCode,
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => $productCode,
            'product_type' => 'sake',
            'name' => $productCode,
            'display_name' => $productCode.' 720ml',
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

        $instruction = app(CreateShipmentInstructionService::class)->create(new CreateShipmentInstructionData(
            instructionDate: '2026-06-21',
            stockLocationId: $location->id,
            lines: [
                new CreateShipmentInstructionLineData($salesOrder->lines->first()->id, $quantity),
            ],
        ));

        return [$instruction, $location];
    }
}
