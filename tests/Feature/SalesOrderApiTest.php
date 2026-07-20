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
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\FoundationPermissionSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\PriceMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_permission_can_create_show_list_and_cancel_sales_order(): void
    {
        [$user, $customer, $product, $unit] = $this->prepareData();

        PriceRule::create([
            'price_list_id' => PriceList::where('code', 'common')->firstOrFail()->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'unit_price' => '1000.0000',
            'priority' => 300,
            'effective_from' => '2026-01-01',
            'rounding_method' => 'round',
        ]);

        $createResponse = $this->actingAs($user)
            ->postJson('/api/v1/sales-orders', [
                'customer_id' => $customer->id,
                'order_date' => '2026-06-20',
                'requested_shipment_date' => '2026-06-22',
                'requested_delivery_date' => '2026-06-23',
                'customer_order_number' => 'API-PO-001',
                'reason' => 'api order input',
                'lines' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => '3.0000',
                        'unit_id' => $unit->id,
                        'note' => 'api line',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.sales_order.status', 'received')
            ->assertJsonPath('data.sales_order.customer_id', $customer->id)
            ->assertJsonPath('data.sales_order.lines.0.quantity', '3.0000')
            ->assertJsonPath('data.sales_order.lines.0.unit_price', '1000.0000')
            ->assertJsonPath('data.sales_order.lines.0.price_source', 'common');

        $salesOrderId = $createResponse->json('data.sales_order.id');
        $salesOrderLineId = $createResponse->json('data.sales_order.lines.0.id');

        $priceRule = PriceRule::create([
            'price_list_id' => PriceList::where('code', 'customer')->firstOrFail()->id,
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'unit_id' => $unit->id,
            'unit_price' => '1200.0000',
            'priority' => 100,
            'effective_from' => '2026-01-01',
            'rounding_method' => 'round',
        ]);

        $this->actingAs($user)
            ->putJson("/api/v1/price-rules/{$priceRule->id}", [
                'unit_price' => '1250.0000',
                'effective_from' => '2026-01-01',
                'reason' => 'renew customer contract price',
            ])
            ->assertOk()
            ->assertJsonPath('data.price_rule.unit_price', '1250.0000');

        $this->actingAs($user)
            ->postJson("/api/v1/sales-orders/{$salesOrderId}/price", ['reason' => 'apply customer price'])
            ->assertOk()
            ->assertJsonPath('data.sales_order.lines.0.unit_price', '1250.0000')
            ->assertJsonPath('data.sales_order.lines.0.price_rule_id', $priceRule->id)
            ->assertJsonPath('data.sales_order.lines.0.price_source', 'customer');

        $this->actingAs($user)
            ->patchJson("/api/v1/sales-orders/{$salesOrderId}/lines/{$salesOrderLineId}/price", [
                'unit_price' => '1150.0000',
                'reason' => 'one-time customer concession',
            ])
            ->assertOk()
            ->assertJsonPath('data.sales_order.lines.0.unit_price', '1150.0000')
            ->assertJsonPath('data.sales_order.lines.0.price_source', 'manual');

        $this->actingAs($user)
            ->postJson("/api/v1/sales-orders/{$salesOrderId}/lines/{$salesOrderLineId}/reprice", [
                'reason' => 'return to applicable price',
            ])
            ->assertOk()
            ->assertJsonPath('data.sales_order.lines.0.unit_price', '1250.0000')
            ->assertJsonPath('data.sales_order.lines.0.price_source', 'customer');

        $this->actingAs($user)
            ->getJson("/api/v1/sales-orders/{$salesOrderId}")
            ->assertOk()
            ->assertJsonPath('data.sales_order.id', $salesOrderId)
            ->assertJsonPath('data.sales_order.lines.0.remaining_quantity', '3.0000');

        $this->actingAs($user)
            ->getJson('/api/v1/sales-orders')
            ->assertOk()
            ->assertJsonPath('data.sales_orders.0.id', $salesOrderId);

        $this->actingAs($user)
            ->postJson("/api/v1/sales-orders/{$salesOrderId}/cancel", [
                'reason' => 'customer cancelled by api',
            ])
            ->assertOk()
            ->assertJsonPath('data.sales_order.status', 'cancelled')
            ->assertJsonPath('data.sales_order.cancelled_reason', 'customer cancelled by api');
    }

    public function test_user_without_sales_order_create_permission_cannot_create_sales_order(): void
    {
        [, $customer, $product, $unit] = $this->prepareData();
        $user = $this->createUser('limited@example.com');

        $this->actingAs($user)
            ->postJson('/api/v1/sales-orders', [
                'customer_id' => $customer->id,
                'order_date' => '2026-06-20',
                'lines' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => '1.0000',
                        'unit_id' => $unit->id,
                    ],
                ],
            ])
            ->assertForbidden()
            ->assertJsonPath('permission', 'sales_order.create');
    }

    public function test_overridden_line_price_can_be_saved_as_customer_specific_price_for_next_order(): void
    {
        [$user, $customer, $product, $unit] = $this->prepareData();

        PriceRule::create([
            'price_list_id' => PriceList::where('code', 'retail_price')->firstOrFail()->id,
            'product_id' => $product->id,
            'transaction_category_id' => $customer->transaction_category_id,
            'unit_id' => $unit->id,
            'unit_price' => '1700.0000',
            'priority' => 200,
            'effective_from' => '2026-01-01',
            'rounding_method' => 'round',
        ]);

        $firstOrder = $this->actingAs($user)->postJson('/api/v1/sales-orders', [
            'customer_id' => $customer->id,
            'order_date' => '2026-06-20',
            'lines' => [[
                'product_id' => $product->id,
                'quantity' => '1.0000',
                'unit_id' => $unit->id,
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.sales_order.lines.0.unit_price', '1700.0000');

        $salesOrderId = $firstOrder->json('data.sales_order.id');
        $lineId = $firstOrder->json('data.sales_order.lines.0.id');

        $this->actingAs($user)->patchJson("/api/v1/sales-orders/{$salesOrderId}/lines/{$lineId}/price", [
            'unit_price' => '1000.0000',
            'reason' => 'customer contract price',
            'save_as_customer_price' => true,
        ])->assertOk()
            ->assertJsonPath('data.sales_order.lines.0.unit_price', '1000.0000')
            ->assertJsonPath('data.sales_order.lines.0.price_source', 'customer');

        $this->assertDatabaseHas('price_rules', [
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'unit_id' => $unit->id,
            'unit_price' => '1000.0000',
            'is_active' => true,
        ]);

        $this->actingAs($user)->postJson('/api/v1/sales-orders', [
            'customer_id' => $customer->id,
            'order_date' => '2026-06-21',
            'lines' => [[
                'product_id' => $product->id,
                'quantity' => '1.0000',
                'unit_id' => $unit->id,
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.sales_order.lines.0.unit_price', '1000.0000')
            ->assertJsonPath('data.sales_order.lines.0.price_source', 'customer');
    }

    public function test_sales_order_create_api_validates_required_lines(): void
    {
        [$user, $customer] = $this->prepareData();

        $this->actingAs($user)
            ->postJson('/api/v1/sales-orders', [
                'customer_id' => $customer->id,
                'order_date' => '2026-06-20',
                'lines' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines']);
    }

    public function test_sales_order_create_api_rejects_an_unpriced_line(): void
    {
        [$user, $customer, $product, $unit] = $this->prepareData();

        $this->actingAs($user)
            ->postJson('/api/v1/sales-orders', [
                'customer_id' => $customer->id,
                'order_date' => '2026-06-20',
                'lines' => [[
                    'product_id' => $product->id,
                    'quantity' => '1.0000',
                    'unit_id' => $unit->id,
                ]],
            ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'business_rule_violation');
    }

    public function test_sales_order_index_filters_and_paginates(): void
    {
        [$user, $customer, $product, $unit] = $this->prepareData();
        PriceRule::create([
            'price_list_id' => PriceList::where('code', 'common')->firstOrFail()->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'unit_price' => '1000.0000',
            'priority' => 300,
            'effective_from' => '2026-01-01',
            'rounding_method' => 'round',
        ]);

        foreach (['LIST-ONE', 'LIST-TWO'] as $number) {
            $this->actingAs($user)->postJson('/api/v1/sales-orders', [
                'customer_id' => $customer->id,
                'order_date' => '2026-06-20',
                'customer_order_number' => $number,
                'lines' => [[
                    'product_id' => $product->id,
                    'quantity' => '1.0000',
                    'unit_id' => $unit->id,
                ]],
            ])->assertCreated();
        }

        $this->actingAs($user)
            ->getJson('/api/v1/sales-orders?status=received&order_date_from=2026-06-20&order_date_to=2026-06-20&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data.sales_orders')
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.pagination.last_page', 2);

        $this->actingAs($user)
            ->getJson('/api/v1/sales-orders?q=LIST-ONE')
            ->assertOk()
            ->assertJsonCount(1, 'data.sales_orders')
            ->assertJsonPath('data.sales_orders.0.customer_order_number', 'LIST-ONE');
    }

    public function test_received_sales_order_can_update_its_lines_and_reapply_price_for_new_line(): void
    {
        [$user, $customer, $product, $unit] = $this->prepareData();
        PriceRule::create([
            'price_list_id' => PriceList::where('code', 'common')->firstOrFail()->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'unit_price' => '1000.0000',
            'priority' => 300,
            'effective_from' => '2026-01-01',
            'rounding_method' => 'round',
        ]);

        $created = $this->actingAs($user)->postJson('/api/v1/sales-orders', [
            'customer_id' => $customer->id,
            'order_date' => '2026-06-20',
            'lines' => [[
                'product_id' => $product->id,
                'quantity' => '1.0000',
                'unit_id' => $unit->id,
            ]],
        ])->assertCreated();

        $salesOrderId = $created->json('data.sales_order.id');
        $lineId = $created->json('data.sales_order.lines.0.id');

        $this->actingAs($user)->putJson("/api/v1/sales-orders/{$salesOrderId}", [
            'requested_delivery_date' => '2026-06-25',
            'customer_order_number' => 'UPDATED-PO',
            'note' => 'updated from order screen',
            'reason' => 'customer changed quantity',
            'lines' => [[
                'id' => $lineId,
                'product_id' => $product->id,
                'quantity' => '3.0000',
                'unit_id' => $unit->id,
            ], [
                'product_id' => $product->id,
                'quantity' => '2.0000',
                'unit_id' => $unit->id,
            ]],
        ])->assertOk()
            ->assertJsonPath('data.sales_order.customer_order_number', 'UPDATED-PO')
            ->assertJsonPath('data.sales_order.lines.0.quantity', '3.0000')
            ->assertJsonPath('data.sales_order.lines.1.unit_price', '1000.0000');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'sales_order.updated',
            'auditable_id' => $salesOrderId,
        ]);
    }

    /**
     * @return array{0: User, 1: Customer, 2: Product, 3: Unit}
     */
    private function prepareData(): array
    {
        $this->seed([
            FoundationPermissionSeeder::class,
            CustomerMasterSeeder::class,
            ProductUnitMasterSeeder::class,
            PriceMasterSeeder::class,
            ShipmentMasterSeeder::class,
        ]);

        $user = $this->createUser('sales-order-admin@example.com');
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail());

        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();
        $unit = Unit::where('code', 'bottle')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'API-SO-CUST-001',
            'name' => 'API Sales Order Customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'API-SO-SAKE-001',
            'product_type' => 'sake',
            'name' => 'API Sales Order Sake',
            'display_name' => 'API Sales Order Sake 720ml',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_alcohol' => true,
        ]);

        return [$user, $customer, $product, $unit];
    }

    private function createUser(string $email): User
    {
        $employee = Employee::create([
            'employee_code' => 'SOAPI'.str_pad((string) (Employee::count() + 1), 3, '0', STR_PAD_LEFT),
            'name' => 'Sales Order API Employee',
            'email' => 'employee-'.$email,
        ]);

        return User::create([
            'employee_id' => $employee->id,
            'name' => 'Sales Order API User',
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);
    }
}
