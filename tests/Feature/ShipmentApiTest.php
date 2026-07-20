<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\PriceList;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\Role;
use App\Models\SettlementReceivableCategory;
use App\Models\ShipmentLine;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_permission_can_create_price_confirm_show_list_and_cancel_shipment(): void
    {
        [$user, $customer, $product, $unit] = $this->prepareData();

        $createResponse = $this->actingAs($user)
            ->postJson('/api/v1/shipments', [
                'customer_id' => $customer->id,
                'document_date' => '2026-06-20',
                'order_date' => '2026-06-19',
                'scheduled_shipment_date' => '2026-06-21',
                'billing_target_date' => '2026-06-20',
                'reason' => 'api shipment draft input',
                'lines' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => '2.0000',
                        'unit_id' => $unit->id,
                        'note' => 'api shipment line',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.shipment.status', 'draft')
            ->assertJsonPath('data.shipment.customer_id', $customer->id)
            ->assertJsonPath('data.shipment.lines.0.quantity', '2.0000');

        $shipmentId = $createResponse->json('data.shipment.id');

        $this->actingAs($user)
            ->postJson("/api/v1/shipments/{$shipmentId}/price", [
                'reason' => 'api shipment pricing',
            ])
            ->assertOk()
            ->assertJsonPath('data.shipment.status', 'draft')
            ->assertJsonPath('data.shipment.lines.0.draft_unit_price', '1800.0000');

        $this->actingAs($user)
            ->postJson("/api/v1/shipments/{$shipmentId}/confirm", [
                'reason' => 'api shipment confirm',
            ])
            ->assertOk()
            ->assertJsonPath('data.shipment.status', 'confirmed')
            ->assertJsonPath('data.shipment.lines.0.confirmed_unit_price', '1800.0000')
            ->assertJsonPath('data.shipment.lines.0.confirmed_liquor_tax_per_kl', '100000.0000');

        $this->actingAs($user)
            ->getJson("/api/v1/shipments/{$shipmentId}")
            ->assertOk()
            ->assertJsonPath('data.shipment.id', $shipmentId)
            ->assertJsonPath('data.shipment.lines.0.confirmed_product_code', $product->product_code);

        $this->actingAs($user)
            ->getJson('/api/v1/shipments')
            ->assertOk()
            ->assertJsonPath('data.shipments.0.id', $shipmentId);

        $this->actingAs($user)
            ->postJson("/api/v1/shipments/{$shipmentId}/cancel", [
                'reason' => 'wrong shipment by api',
            ])
            ->assertOk()
            ->assertJsonPath('data.shipment.status', 'cancelled')
            ->assertJsonPath('data.shipment.cancelled_reason', 'wrong shipment by api');
    }

    public function test_user_without_shipment_create_permission_cannot_create_shipment(): void
    {
        [, $customer, $product, $unit] = $this->prepareData();
        $user = $this->createUser('limited-shipment@example.com');

        $this->actingAs($user)
            ->postJson('/api/v1/shipments', [
                'customer_id' => $customer->id,
                'document_date' => '2026-06-20',
                'lines' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => '1.0000',
                        'unit_id' => $unit->id,
                    ],
                ],
            ])
            ->assertForbidden()
            ->assertJsonPath('permission', 'shipment.create');
    }

    public function test_shipment_create_api_validates_required_lines(): void
    {
        [$user, $customer] = $this->prepareData();

        $this->actingAs($user)
            ->postJson('/api/v1/shipments', [
                'customer_id' => $customer->id,
                'document_date' => '2026-06-20',
                'lines' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines']);
    }

    public function test_shipment_detail_returns_the_display_name_and_capacity(): void
    {
        [$user, $customer, $product, $unit] = $this->prepareData();

        $response = $this->actingAs($user)->postJson('/api/v1/shipments', [
            'customer_id' => $customer->id,
            'document_date' => '2026-06-20',
            'lines' => [[
                'product_id' => $product->id,
                'quantity' => '2.0000',
                'unit_id' => $unit->id,
            ]],
        ])->assertCreated();
        $shipmentId = $response->json('data.shipment.id');

        ShipmentLine::query()->where('shipment_header_id', $shipmentId)->update([
            'confirmed_product_name' => 'API Shipment Sake',
            'confirmed_display_name' => 'API Shipment Sake 720ml',
            'confirmed_capacity_value' => '720.0000',
            'confirmed_capacity_unit_id' => $product->capacity_unit_id,
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/shipments/{$shipmentId}")
            ->assertOk()
            ->assertJsonPath('data.shipment.lines.0.product_name', 'API Shipment Sake 720ml')
            ->assertJsonPath('data.shipment.lines.0.capacity_value', '720.0000')
            ->assertJsonPath('data.shipment.lines.0.capacity_unit_name', 'ml');
    }

    /**
     * @return array{0: User, 1: Customer, 2: Product, 3: Unit}
     */
    private function prepareData(): array
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->createUser('shipment-admin@example.com');
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail());

        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();
        $bottle = Unit::where('code', 'bottle')->firstOrFail();
        $milliliter = Unit::where('code', 'milliliter')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'API-SH-CUST-001',
            'name' => 'API Shipment Customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'API-SH-SAKE-001',
            'product_type' => 'sake',
            'name' => 'API Shipment Sake',
            'display_name' => 'API Shipment Sake 720ml',
            'base_unit_id' => $bottle->id,
            'sales_unit_id' => $bottle->id,
            'inventory_unit_id' => $bottle->id,
            'capacity_value' => '720.0000',
            'capacity_unit_id' => $milliliter->id,
            'alcohol_percentage' => '15.50',
            'is_alcohol' => true,
        ]);

        PriceRule::create([
            'price_list_id' => PriceList::where('code', 'common')->firstOrFail()->id,
            'product_id' => $product->id,
            'unit_id' => $bottle->id,
            'unit_price' => '1800.0000',
            'priority' => 300,
            'effective_from' => '2026-01-01',
            'rounding_method' => 'round',
        ]);

        return [$user, $customer, $product, $bottle];
    }

    private function createUser(string $email): User
    {
        $employee = Employee::create([
            'employee_code' => 'SHAPI'.str_pad((string) (Employee::count() + 1), 3, '0', STR_PAD_LEFT),
            'name' => 'Shipment API Employee',
            'email' => 'employee-'.$email,
        ]);

        return User::create([
            'employee_id' => $employee->id,
            'name' => 'Shipment API User',
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);
    }
}
