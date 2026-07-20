<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\Role;
use App\Models\SettlementReceivableCategory;
use App\Models\ShipmentHeader;
use App\Models\ShipmentLine;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_permission_can_view_and_allocate_lot_stock(): void
    {
        [$user, $shipmentLine, $product, $unit, $location, $lot] = $this->prepareData();

        $this->actingAs($user)
            ->getJson("/api/v1/inventory/stock?stock_location_id={$location->id}")
            ->assertOk()
            ->assertJsonPath('data.inventory_basis', 'production_lot')
            ->assertJsonPath('data.stock_balances.0.physical_quantity', '5.0000')
            ->assertJsonPath('data.stock_balances.0.available_quantity', '5.0000');

        $this->actingAs($user)
            ->postJson('/api/v1/inventory/allocate', [
                'shipment_line_id' => $shipmentLine->id,
                'production_lot_id' => $lot->id,
                'stock_location_id' => $location->id,
                'quantity' => '1.0000',
                'reason' => 'api allocate',
            ])
            ->assertCreated()
            ->assertJsonPath('data.allocation.status', 'allocated')
            ->assertJsonPath('data.allocation.quantity', '1.0000');

        $this->actingAs($user)
            ->getJson("/api/v1/inventory/lot-stock?production_lot_id={$lot->id}&stock_location_id={$location->id}&unit_id={$unit->id}")
            ->assertOk()
            ->assertJsonPath('data.lot_stock_balance.physical_quantity', '5.0000')
            ->assertJsonPath('data.lot_stock_balance.allocated_quantity', '1.0000')
            ->assertJsonPath('data.lot_stock_balance.available_quantity', '4.0000');
    }

    public function test_user_without_inventory_allocate_permission_cannot_allocate_lot(): void
    {
        [, $shipmentLine,, $unit, $location, $lot] = $this->prepareData();
        $user = $this->createUser('limited-inventory@example.com');

        $this->actingAs($user)
            ->postJson('/api/v1/inventory/allocate', [
                'shipment_line_id' => $shipmentLine->id,
                'production_lot_id' => $lot->id,
                'stock_location_id' => $location->id,
                'quantity' => '1.0000',
            ])
            ->assertForbidden()
            ->assertJsonPath('permission', 'inventory.allocate');
    }

    public function test_inventory_allocate_api_validates_required_quantity(): void
    {
        [$user, $shipmentLine,, $unit, $location, $lot] = $this->prepareData();

        $this->actingAs($user)
            ->postJson('/api/v1/inventory/allocate', [
                'shipment_line_id' => $shipmentLine->id,
                'production_lot_id' => $lot->id,
                'stock_location_id' => $location->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_zero_stock_is_hidden_by_default_but_negative_stock_is_always_visible(): void
    {
        [$user,, $product, $unit, $location, $lot] = $this->prepareData();

        $zeroLot = $lot->replicate()->fill([
            'lot_code' => 'API-INV-LOT-ZERO',
            'display_name' => 'API Inventory Zero Lot',
        ]);
        $zeroLot->save();
        $this->createStock($product, $unit, $location, $zeroLot, '3.0000');
        $this->createStock($product, $unit, $location, $zeroLot, '-3.0000');

        $negativeLot = $lot->replicate()->fill([
            'lot_code' => 'API-INV-LOT-NEGATIVE',
            'display_name' => 'API Inventory Negative Lot',
        ]);
        $negativeLot->save();
        $this->createStock($product, $unit, $location, $negativeLot, '-2.0000');

        $this->actingAs($user)
            ->getJson('/api/v1/inventory/stock')
            ->assertOk()
            ->assertJsonPath('data.zero_stock_hidden', true)
            ->assertJsonMissing(['production_lot_id' => $zeroLot->id])
            ->assertJsonFragment([
                'production_lot_id' => $negativeLot->id,
                'physical_quantity' => '-2.0000',
            ]);

        $this->actingAs($user)
            ->getJson('/api/v1/inventory/stock?include_zero_stock=1')
            ->assertOk()
            ->assertJsonPath('data.zero_stock_hidden', false)
            ->assertJsonFragment([
                'production_lot_id' => $zeroLot->id,
                'physical_quantity' => '0.0000',
            ]);
    }

    /**
     * @return array{0: User, 1: ShipmentLine, 2: Product, 3: Unit, 4: StockLocation, 5: ProductionLot}
     */
    private function prepareData(): array
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->createUser('inventory-admin@example.com');
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail());

        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();
        $bottle = Unit::where('code', 'bottle')->firstOrFail();
        $milliliter = Unit::where('code', 'milliliter')->firstOrFail();
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'API-INV-CUST-001',
            'name' => 'API Inventory Customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'API-INV-SAKE-001',
            'product_type' => 'sake',
            'name' => 'API Inventory Sake',
            'display_name' => 'API Inventory Sake 720ml',
            'base_unit_id' => $bottle->id,
            'sales_unit_id' => $bottle->id,
            'inventory_unit_id' => $bottle->id,
            'capacity_value' => '720.0000',
            'capacity_unit_id' => $milliliter->id,
            'alcohol_percentage' => '15.50',
            'is_alcohol' => true,
            'is_inventory_managed' => true,
        ]);

        $shipment = ShipmentHeader::create([
            'document_number' => 'API-INV-SHIP-001',
            'status' => 'draft',
            'customer_id' => $customer->id,
            'transaction_category_id' => $customer->transaction_category_id,
            'settlement_receivable_category_id' => $customer->settlement_receivable_category_id,
            'billing_cycle_id' => $customer->billing_cycle_id,
            'document_date' => '2026-06-20',
        ]);

        $shipmentLine = ShipmentLine::create([
            'shipment_header_id' => $shipment->id,
            'line_no' => 1,
            'product_id' => $product->id,
            'quantity' => '3.0000',
            'unit_id' => $bottle->id,
        ]);

        $lot = ProductionLot::create([
            'lot_code' => 'API-INV-LOT-001',
            'display_name' => 'API Inventory Lot 001',
            'stock_location_id' => $location->id,
            'unit_id' => $bottle->id,
            'capacity_value' => '720.0000',
            'capacity_unit_id' => $milliliter->id,
            'alcohol_percentage' => '15.50',
            'analysis_status' => 'confirmed',
            'production_date' => '2026-06-01',
        ]);

        $this->createStock($product, $bottle, $location, $lot, '5.0000');

        return [$user, $shipmentLine, $product, $bottle, $location, $lot];
    }

    private function createStock(
        Product $product,
        Unit $unit,
        StockLocation $location,
        ProductionLot $lot,
        string $quantity,
    ): void {
        StockMovement::create([
            'status' => 'confirmed',
            'movement_type' => 'inventory_adjustment',
            'movement_date' => '2026-06-10',
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'quantity' => $quantity,
            'production_lot_id' => $lot->id,
            'lot_code' => $lot->lot_code,
            'confirmed_at' => now(),
        ]);
    }

    private function createUser(string $email): User
    {
        $employee = Employee::create([
            'employee_code' => 'INVAPI'.str_pad((string) (Employee::count() + 1), 3, '0', STR_PAD_LEFT),
            'name' => 'Inventory API Employee',
            'email' => 'employee-'.$email,
        ]);

        return User::create([
            'employee_id' => $employee->id,
            'name' => 'Inventory API User',
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);
    }
}
