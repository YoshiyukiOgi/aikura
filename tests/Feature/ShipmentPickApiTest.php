<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SettlementReceivableCategory;
use App\Models\ShipmentInstruction;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Models\User;
use App\Services\SalesOrder\CreateSalesOrderData;
use App\Services\SalesOrder\CreateSalesOrderLineData;
use App\Services\SalesOrder\CreateSalesOrderService;
use App\Services\ShipmentInstruction\CreateShipmentInstructionData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionLineData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\FoundationPermissionSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Database\Seeders\StockLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentPickApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_permission_can_create_show_list_and_cancel_shipment_pick(): void
    {
        [$user, $instruction, $location] = $this->prepareData();
        $instructionLine = $instruction->lines->first();

        $createResponse = $this->actingAs($user)
            ->postJson('/api/v1/shipment-picks', [
                'shipment_instruction_id' => $instruction->id,
                'pick_date' => '2026-06-22',
                'stock_location_id' => $location->id,
                'reason' => 'api pick input',
                'lines' => [
                    [
                        'shipment_instruction_line_id' => $instructionLine->id,
                        'quantity' => '4.0000',
                        'note' => 'api pick line',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.shipment_pick.status', 'picked')
            ->assertJsonPath('data.shipment_pick.shipment_instruction_id', $instruction->id)
            ->assertJsonPath('data.shipment_pick.lines.0.quantity', '4.0000');

        $pickId = $createResponse->json('data.shipment_pick.id');

        $this->assertSame('4.0000', $instructionLine->refresh()->picked_quantity);
        $this->assertSame('partially_picked', $instruction->refresh()->status);

        $this->actingAs($user)
            ->getJson("/api/v1/shipment-picks/{$pickId}")
            ->assertOk()
            ->assertJsonPath('data.shipment_pick.id', $pickId)
            ->assertJsonPath('data.shipment_pick.lines.0.shipment_instruction_line_id', $instructionLine->id);

        $this->actingAs($user)
            ->getJson('/api/v1/shipment-picks')
            ->assertOk()
            ->assertJsonPath('data.shipment_picks.0.id', $pickId);

        $this->actingAs($user)
            ->postJson("/api/v1/shipment-picks/{$pickId}/cancel", [
                'reason' => 'wrong pick by api',
            ])
            ->assertOk()
            ->assertJsonPath('data.shipment_pick.status', 'cancelled')
            ->assertJsonPath('data.shipment_pick.cancelled_reason', 'wrong pick by api');

        $this->assertSame('0.0000', $instructionLine->refresh()->picked_quantity);
        $this->assertSame('instructed', $instruction->refresh()->status);
    }

    public function test_user_without_shipment_pick_create_permission_cannot_create_pick(): void
    {
        [, $instruction, $location] = $this->prepareData();
        $user = $this->createUser('limited-pick@example.com');

        $this->actingAs($user)
            ->postJson('/api/v1/shipment-picks', [
                'shipment_instruction_id' => $instruction->id,
                'pick_date' => '2026-06-22',
                'stock_location_id' => $location->id,
                'lines' => [
                    [
                        'shipment_instruction_line_id' => $instruction->lines->first()->id,
                        'quantity' => '1.0000',
                    ],
                ],
            ])
            ->assertForbidden()
            ->assertJsonPath('permission', 'shipment_pick.create');
    }

    public function test_shipment_pick_create_api_validates_required_lines(): void
    {
        [$user, $instruction] = $this->prepareData();

        $this->actingAs($user)
            ->postJson('/api/v1/shipment-picks', [
                'shipment_instruction_id' => $instruction->id,
                'pick_date' => '2026-06-22',
                'lines' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines']);
    }

    public function test_lot_candidates_hide_zero_stock_and_expose_negative_stock_as_non_selectable(): void
    {
        [$user, $instruction, $location] = $this->prepareData();
        $instructionLine = $instruction->lines->firstOrFail();

        $zeroLot = ProductionLot::create([
            'lot_code' => 'API-SP-LOT-ZERO',
            'display_name' => 'API Shipment Pick Zero Lot',
            'stock_location_id' => $location->id,
            'unit_id' => $instructionLine->unit_id,
            'analysis_status' => 'confirmed',
        ]);
        $negativeLot = ProductionLot::create([
            'lot_code' => 'API-SP-LOT-NEGATIVE',
            'display_name' => 'API Shipment Pick Negative Lot',
            'stock_location_id' => $location->id,
            'unit_id' => $instructionLine->unit_id,
            'analysis_status' => 'confirmed',
        ]);

        $this->createLotMovement($zeroLot, $location, $instructionLine->unit_id, '2.0000');
        $this->createLotMovement($zeroLot, $location, $instructionLine->unit_id, '-2.0000');
        $this->createLotMovement($negativeLot, $location, $instructionLine->unit_id, '-1.0000');

        $this->actingAs($user)
            ->getJson("/api/v1/shipment-instructions/{$instruction->id}/lines/{$instructionLine->id}/lots")
            ->assertOk()
            ->assertJsonMissing(['production_lot_id' => $zeroLot->id])
            ->assertJsonFragment([
                'production_lot_id' => $negativeLot->id,
                'stock_status' => 'negative',
                'selectable' => false,
                'available_quantity' => '-1.0000',
            ]);
    }

    /**
     * @return array{0: User, 1: ShipmentInstruction, 2: StockLocation}
     */
    private function prepareData(): array
    {
        $this->seed([
            FoundationPermissionSeeder::class,
            CustomerMasterSeeder::class,
            ProductUnitMasterSeeder::class,
            ShipmentMasterSeeder::class,
            StockLocationSeeder::class,
        ]);

        $user = $this->createUser('shipment-pick-admin@example.com');
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail());

        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();
        $unit = Unit::where('code', 'bottle')->firstOrFail();
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'API-SP-CUST-001',
            'name' => 'API Shipment Pick Customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'API-SP-SAKE-001',
            'product_type' => 'sake',
            'name' => 'API Shipment Pick Sake',
            'display_name' => 'API Shipment Pick Sake 720ml',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_alcohol' => true,
        ]);

        $salesOrder = app(CreateSalesOrderService::class)->create(new CreateSalesOrderData(
            customerId: $customer->id,
            orderDate: '2026-06-20',
            lines: [
                new CreateSalesOrderLineData($product->id, '10.0000', $unit->id),
            ],
        ));

        $instruction = $this->createInstruction($salesOrder, $location);

        return [$user, $instruction, $location];
    }

    private function createInstruction(SalesOrder $salesOrder, StockLocation $location): ShipmentInstruction
    {
        return app(CreateShipmentInstructionService::class)->create(new CreateShipmentInstructionData(
            instructionDate: '2026-06-21',
            scheduledShipmentDate: '2026-06-22',
            stockLocationId: $location->id,
            lines: [
                new CreateShipmentInstructionLineData($salesOrder->lines->first()->id, '10.0000'),
            ],
        ));
    }

    private function createLotMovement(ProductionLot $lot, StockLocation $location, int $unitId, string $quantity): void
    {
        StockMovement::create([
            'status' => 'confirmed',
            'movement_type' => 'inventory_adjustment',
            'movement_date' => '2026-06-20',
            'stock_location_id' => $location->id,
            'unit_id' => $unitId,
            'quantity' => $quantity,
            'production_lot_id' => $lot->id,
            'lot_code' => $lot->lot_code,
            'confirmed_at' => now(),
        ]);
    }

    private function createUser(string $email): User
    {
        $employee = Employee::create([
            'employee_code' => 'SPAPI'.str_pad((string) (Employee::count() + 1), 3, '0', STR_PAD_LEFT),
            'name' => 'Shipment Pick API Employee',
            'email' => 'employee-'.$email,
        ]);

        return User::create([
            'employee_id' => $employee->id,
            'name' => 'Shipment Pick API User',
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);
    }
}
