<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SettlementReceivableCategory;
use App\Models\StockLocation;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Models\User;
use App\Services\SalesOrder\CreateSalesOrderData;
use App\Services\SalesOrder\CreateSalesOrderLineData;
use App\Services\SalesOrder\CreateSalesOrderService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\FoundationPermissionSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Database\Seeders\StockLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentInstructionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_permission_can_create_show_list_and_cancel_shipment_instruction(): void
    {
        [$user, $salesOrder, $location] = $this->prepareData();
        $salesOrderLine = $salesOrder->lines->first();

        $createResponse = $this->actingAs($user)
            ->postJson('/api/v1/shipment-instructions', [
                'instruction_date' => '2026-06-21',
                'scheduled_shipment_date' => '2026-06-22',
                'stock_location_id' => $location->id,
                'reason' => 'api instruction input',
                'lines' => [
                    [
                        'sales_order_line_id' => $salesOrderLine->id,
                        'quantity' => '4.0000',
                        'note' => 'api instruction line',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.shipment_instruction.status', 'instructed')
            ->assertJsonPath('data.shipment_instruction.customer_id', $salesOrder->customer_id)
            ->assertJsonPath('data.shipment_instruction.lines.0.quantity', '4.0000');

        $instructionId = $createResponse->json('data.shipment_instruction.id');

        $this->assertSame('6.0000', $salesOrderLine->refresh()->remaining_quantity);
        $this->assertSame('partially_instructed', $salesOrder->refresh()->status);

        $this->actingAs($user)
            ->getJson("/api/v1/shipment-instructions/{$instructionId}")
            ->assertOk()
            ->assertJsonPath('data.shipment_instruction.id', $instructionId)
            ->assertJsonPath('data.shipment_instruction.lines.0.sales_order_line_id', $salesOrderLine->id);

        $this->actingAs($user)
            ->getJson('/api/v1/shipment-instructions')
            ->assertOk()
            ->assertJsonPath('data.shipment_instructions.0.id', $instructionId);

        $this->actingAs($user)
            ->postJson("/api/v1/shipment-instructions/{$instructionId}/cancel", [
                'reason' => 'wrong instruction by api',
            ])
            ->assertOk()
            ->assertJsonPath('data.shipment_instruction.status', 'cancelled')
            ->assertJsonPath('data.shipment_instruction.cancelled_reason', 'wrong instruction by api');

        $this->assertSame('10.0000', $salesOrderLine->refresh()->remaining_quantity);
        $this->assertSame('received', $salesOrder->refresh()->status);
    }

    public function test_user_without_shipment_instruction_create_permission_cannot_create_instruction(): void
    {
        [, $salesOrder, $location] = $this->prepareData();
        $user = $this->createUser('limited-instruction@example.com');

        $this->actingAs($user)
            ->postJson('/api/v1/shipment-instructions', [
                'instruction_date' => '2026-06-21',
                'stock_location_id' => $location->id,
                'lines' => [
                    [
                        'sales_order_line_id' => $salesOrder->lines->first()->id,
                        'quantity' => '1.0000',
                    ],
                ],
            ])
            ->assertForbidden()
            ->assertJsonPath('permission', 'shipment_instruction.create');
    }

    public function test_shipment_instruction_create_api_validates_required_lines(): void
    {
        [$user] = $this->prepareData();

        $this->actingAs($user)
            ->postJson('/api/v1/shipment-instructions', [
                'instruction_date' => '2026-06-21',
                'lines' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines']);
    }

    /**
     * @return array{0: User, 1: SalesOrder, 2: StockLocation}
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

        $user = $this->createUser('shipment-instruction-admin@example.com');
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail());

        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();
        $unit = Unit::where('code', 'bottle')->firstOrFail();
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'API-SI-CUST-001',
            'name' => 'API Shipment Instruction Customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'API-SI-SAKE-001',
            'product_type' => 'sake',
            'name' => 'API Shipment Instruction Sake',
            'display_name' => 'API Shipment Instruction Sake 720ml',
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

        return [$user, $salesOrder, $location];
    }

    private function createUser(string $email): User
    {
        $employee = Employee::create([
            'employee_code' => 'SIAPI'.str_pad((string) (Employee::count() + 1), 3, '0', STR_PAD_LEFT),
            'name' => 'Shipment Instruction API Employee',
            'email' => 'employee-'.$email,
        ]);

        return User::create([
            'employee_id' => $employee->id,
            'name' => 'Shipment Instruction API User',
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);
    }
}
