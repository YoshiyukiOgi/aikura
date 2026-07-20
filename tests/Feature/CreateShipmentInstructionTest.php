<?php

namespace Tests\Feature;

use App\Exceptions\Shipment\ShipmentInstructionException;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SettlementReceivableCategory;
use App\Models\StockLocation;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Services\SalesOrder\CreateSalesOrderData;
use App\Services\SalesOrder\CreateSalesOrderLineData;
use App\Services\SalesOrder\CreateSalesOrderService;
use App\Services\ShipmentInstruction\CreateShipmentInstructionData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionLineData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Database\Seeders\StockLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CreateShipmentInstructionTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipment_instruction_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('shipment_instructions'));
        $this->assertTrue(Schema::hasTable('shipment_instruction_lines'));

        foreach ([
            'instruction_number',
            'status',
            'customer_id',
            'instruction_date',
            'scheduled_shipment_date',
            'stock_location_id',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('shipment_instructions', $column), "Column [shipment_instructions.{$column}] does not exist.");
        }

        foreach ([
            'shipment_instruction_id',
            'line_no',
            'sales_order_id',
            'sales_order_line_id',
            'product_id',
            'quantity',
            'unit_id',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('shipment_instruction_lines', $column), "Column [shipment_instruction_lines.{$column}] does not exist.");
        }
    }

    public function test_it_creates_shipment_instruction_from_sales_order_line(): void
    {
        [$salesOrder, $location] = $this->prepareSalesOrder('10.0000');
        $salesOrderLine = $salesOrder->lines->first();

        $instruction = app(CreateShipmentInstructionService::class)->create(new CreateShipmentInstructionData(
            instructionDate: '2026-06-21',
            scheduledShipmentDate: '2026-06-22',
            stockLocationId: $location->id,
            reason: 'instruction test',
            lines: [
                new CreateShipmentInstructionLineData($salesOrderLine->id, '4.0000', 'first instruction'),
            ],
        ));

        $salesOrderLine->refresh();
        $salesOrder->refresh();

        $this->assertSame('instructed', $instruction->status);
        $this->assertStringStartsWith('SI-', $instruction->instruction_number);
        $this->assertSame($salesOrder->customer_id, $instruction->customer_id);
        $this->assertSame($location->id, $instruction->stock_location_id);
        $this->assertCount(1, $instruction->lines);
        $this->assertSame($salesOrder->id, $instruction->lines->first()->sales_order_id);
        $this->assertSame($salesOrderLine->id, $instruction->lines->first()->sales_order_line_id);
        $this->assertSame('4.0000', $instruction->lines->first()->quantity);
        $this->assertSame('6.0000', $salesOrderLine->remaining_quantity);
        $this->assertSame('partially_instructed', $salesOrder->status);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'shipment_instruction.created',
            'target_table' => 'shipment_instructions',
            'target_id' => (string) $instruction->id,
            'reason' => 'instruction test',
        ]);
    }

    public function test_it_marks_sales_order_instructed_when_all_remaining_quantity_is_used(): void
    {
        [$salesOrder] = $this->prepareSalesOrder('10.0000');
        $salesOrderLine = $salesOrder->lines->first();

        app(CreateShipmentInstructionService::class)->create(new CreateShipmentInstructionData(
            instructionDate: '2026-06-21',
            lines: [
                new CreateShipmentInstructionLineData($salesOrderLine->id, '10.0000'),
            ],
        ));

        $salesOrderLine->refresh();
        $salesOrder->refresh();

        $this->assertSame('0.0000', $salesOrderLine->remaining_quantity);
        $this->assertSame('instructed', $salesOrder->status);
    }

    public function test_it_rejects_empty_lines(): void
    {
        $this->expectException(ShipmentInstructionException::class);

        app(CreateShipmentInstructionService::class)->create(new CreateShipmentInstructionData(
            instructionDate: '2026-06-21',
            lines: [],
        ));
    }

    public function test_it_rejects_non_positive_quantity(): void
    {
        [$salesOrder] = $this->prepareSalesOrder('10.0000');
        $salesOrderLine = $salesOrder->lines->first();

        $this->expectException(ShipmentInstructionException::class);

        app(CreateShipmentInstructionService::class)->create(new CreateShipmentInstructionData(
            instructionDate: '2026-06-21',
            lines: [
                new CreateShipmentInstructionLineData($salesOrderLine->id, '0.0000'),
            ],
        ));
    }

    public function test_it_rejects_quantity_over_remaining(): void
    {
        [$salesOrder] = $this->prepareSalesOrder('3.0000');
        $salesOrderLine = $salesOrder->lines->first();

        $this->expectException(ShipmentInstructionException::class);

        app(CreateShipmentInstructionService::class)->create(new CreateShipmentInstructionData(
            instructionDate: '2026-06-21',
            lines: [
                new CreateShipmentInstructionLineData($salesOrderLine->id, '4.0000'),
            ],
        ));
    }

    public function test_it_rejects_duplicate_sales_order_line_in_same_instruction(): void
    {
        [$salesOrder] = $this->prepareSalesOrder('10.0000');
        $salesOrderLine = $salesOrder->lines->first();

        $this->expectException(ShipmentInstructionException::class);

        app(CreateShipmentInstructionService::class)->create(new CreateShipmentInstructionData(
            instructionDate: '2026-06-21',
            lines: [
                new CreateShipmentInstructionLineData($salesOrderLine->id, '1.0000'),
                new CreateShipmentInstructionLineData($salesOrderLine->id, '1.0000'),
            ],
        ));
    }

    public function test_it_rejects_lines_for_different_customers(): void
    {
        [$firstOrder] = $this->prepareSalesOrder('3.0000', 'INST-CUST-001', 'INST-SAKE-001');
        [$secondOrder] = $this->prepareSalesOrder('3.0000', 'INST-CUST-002', 'INST-SAKE-002');

        $this->expectException(ShipmentInstructionException::class);

        app(CreateShipmentInstructionService::class)->create(new CreateShipmentInstructionData(
            instructionDate: '2026-06-21',
            lines: [
                new CreateShipmentInstructionLineData($firstOrder->lines->first()->id, '1.0000'),
                new CreateShipmentInstructionLineData($secondOrder->lines->first()->id, '1.0000'),
            ],
        ));
    }

    public function test_it_rejects_lines_for_different_settlement_receivable_categories(): void
    {
        [$firstOrder] = $this->prepareSalesOrder('3.0000', 'INST-CUST-001', 'INST-SAKE-001');
        $customer = $firstOrder->customer;
        $unit = Unit::where('code', 'bottle')->firstOrFail();
        $exportCategory = SettlementReceivableCategory::where('code', 'export')->firstOrFail();
        $product = Product::create([
            'product_code' => 'INST-SAKE-002',
            'product_type' => 'sake',
            'name' => 'INST-SAKE-002',
            'display_name' => 'INST-SAKE-002 720ml',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_alcohol' => true,
        ]);
        $secondOrder = app(CreateSalesOrderService::class)->create(new CreateSalesOrderData(
            customerId: $customer->id,
            orderDate: '2026-06-20',
            settlementReceivableCategoryId: $exportCategory->id,
            lines: [
                new CreateSalesOrderLineData($product->id, '3.0000', $unit->id),
            ],
        ));

        $this->expectException(ShipmentInstructionException::class);

        app(CreateShipmentInstructionService::class)->create(new CreateShipmentInstructionData(
            instructionDate: '2026-06-21',
            lines: [
                new CreateShipmentInstructionLineData($firstOrder->lines->first()->id, '1.0000'),
                new CreateShipmentInstructionLineData($secondOrder->lines->first()->id, '1.0000'),
            ],
        ));
    }

    public function test_shipment_instruction_number_sequence_increments(): void
    {
        [$salesOrder] = $this->prepareSalesOrder('10.0000');
        $salesOrderLine = $salesOrder->lines->first();
        $service = app(CreateShipmentInstructionService::class);

        $first = $service->create(new CreateShipmentInstructionData(
            instructionDate: '2026-06-21',
            lines: [
                new CreateShipmentInstructionLineData($salesOrderLine->id, '1.0000'),
            ],
        ));

        $second = $service->create(new CreateShipmentInstructionData(
            instructionDate: '2026-06-21',
            lines: [
                new CreateShipmentInstructionLineData($salesOrderLine->id, '1.0000'),
            ],
        ));

        $this->assertNotSame($first->instruction_number, $second->instruction_number);
    }

    /**
     * @return array{0: SalesOrder, 1: StockLocation}
     */
    private function prepareSalesOrder(
        string $quantity,
        string $customerCode = 'INST-CUST-001',
        string $productCode = 'INST-SAKE-001',
        string $settlementCategoryCode = 'accounts_receivable_1',
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
        $settlementCategory = SettlementReceivableCategory::where('code', $settlementCategoryCode)->firstOrFail();
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
        ]);

        $salesOrder = app(CreateSalesOrderService::class)->create(new CreateSalesOrderData(
            customerId: $customer->id,
            orderDate: '2026-06-20',
            lines: [
                new CreateSalesOrderLineData($product->id, $quantity, $unit->id),
            ],
        ));

        return [$salesOrder, $location];
    }
}
