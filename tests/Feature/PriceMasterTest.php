<?php

namespace Tests\Feature;

use App\Exceptions\Pricing\PriceResolutionException;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\PriceList;
use App\Models\PriceReviewTask;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\Role;
use App\Models\SettlementReceivableCategory;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Models\User;
use App\Services\Pricing\ResolvePriceService;
use App\Services\Pricing\CreatePriceReviewTasksService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\FoundationPermissionSeeder;
use Database\Seeders\PriceMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PriceMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_tables_exist_and_products_do_not_have_price_column(): void
    {
        foreach (['price_lists', 'price_rules', 'price_review_tasks'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table [{$table}] does not exist.");
        }

        foreach (['price', 'unit_price', 'standard_price'] as $column) {
            $this->assertFalse(Schema::hasColumn('products', $column), "Forbidden price column [products.{$column}] exists.");
        }
    }

    public function test_price_master_seed_creates_price_lists(): void
    {
        $this->seed(PriceMasterSeeder::class);

        foreach (['standard', 'common', 'transaction_category', 'customer', 'producer_price', 'wholesale_price', 'retail_price', 'customer_price'] as $code) {
            $this->assertDatabaseHas('price_lists', [
                'code' => $code,
                'is_active' => true,
            ]);
        }
    }

    public function test_customer_specific_price_has_highest_priority(): void
    {
        [$customer, $product, $unit] = $this->prepareCustomerProduct();

        $standard = PriceList::where('code', 'standard')->firstOrFail();
        $common = PriceList::where('code', 'common')->firstOrFail();
        $group = PriceList::where('code', 'transaction_category')->firstOrFail();
        $customerList = PriceList::where('code', 'customer')->firstOrFail();

        $this->createRule($standard, $product, $unit, '1500.0000', 400, []);
        $this->createRule($common, $product, $unit, '1400.0000', 300, []);
        $this->createRule($group, $product, $unit, '1300.0000', 200, [
            'transaction_category_id' => $customer->transaction_category_id,
        ]);
        $this->createRule($customerList, $product, $unit, '1200.0000', 100, [
            'customer_id' => $customer->id,
        ]);

        $resolved = app(ResolvePriceService::class)->resolve($customer, $product, '2026-05-23');

        $this->assertSame('1200.0000', $resolved->unitPrice);
        $this->assertSame('customer', $resolved->source);
        $this->assertSame('取引先個別価格', $resolved->reason);
        $this->assertSame($customerList->id, $resolved->priceListId);
    }

    public function test_transaction_category_price_is_used_before_common_price(): void
    {
        [$customer, $product, $unit] = $this->prepareCustomerProduct();

        $common = PriceList::where('code', 'common')->firstOrFail();
        $group = PriceList::where('code', 'transaction_category')->firstOrFail();

        $this->createRule($common, $product, $unit, '1400.0000', 300, []);
        $this->createRule($group, $product, $unit, '1300.0000', 200, [
            'transaction_category_id' => $customer->transaction_category_id,
        ]);

        $resolved = app(ResolvePriceService::class)->resolve($customer, $product, '2026-05-23');

        $this->assertSame('1300.0000', $resolved->unitPrice);
        $this->assertSame('transaction_category', $resolved->source);
    }

    public function test_effective_period_is_respected(): void
    {
        [$customer, $product, $unit] = $this->prepareCustomerProduct();

        $common = PriceList::where('code', 'common')->firstOrFail();

        $this->createRule($common, $product, $unit, '1000.0000', 300, [
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-04-30',
        ]);
        $this->createRule($common, $product, $unit, '1100.0000', 300, [
            'effective_from' => '2026-05-01',
        ]);

        $resolved = app(ResolvePriceService::class)->resolve($customer, $product, '2026-05-23');

        $this->assertSame('1100.0000', $resolved->unitPrice);
    }

    public function test_missing_price_throws_exception(): void
    {
        [$customer, $product] = $this->prepareCustomerProduct();

        $this->expectException(PriceResolutionException::class);

        app(ResolvePriceService::class)->resolve($customer, $product, '2026-05-23');
    }

    public function test_price_review_task_is_created_when_category_price_changes(): void
    {
        [$customer, $product, $unit] = $this->prepareCustomerProduct();

        $group = PriceList::where('code', 'wholesale_price')->firstOrFail();
        $customerList = PriceList::where('code', 'customer_price')->firstOrFail();

        $categoryRule = $this->createRule($group, $product, $unit, '1300.0000', 200, [
            'transaction_category_id' => $customer->transaction_category_id,
        ]);
        $customerRule = $this->createRule($customerList, $product, $unit, '1200.0000', 100, [
            'customer_id' => $customer->id,
        ]);

        $categoryRule->unit_price = '1400.0000';
        $categoryRule->save();

        $created = app(CreatePriceReviewTasksService::class)->createForChangedRule(
            changedRule: $categoryRule,
            oldUnitPrice: '1300.0000',
            newUnitPrice: '1400.0000',
            reason: 'wholesale price revision',
        );

        $this->assertSame(1, $created);
        $this->assertDatabaseHas('price_review_tasks', [
            'changed_price_rule_id' => $categoryRule->id,
            'affected_price_rule_id' => $customerRule->id,
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'status' => PriceReviewTask::STATUS_PENDING,
            'old_reference_price' => '1300.0000',
            'new_reference_price' => '1400.0000',
            'current_individual_price' => '1200.0000',
        ]);
    }

    public function test_price_review_task_notification_is_returned_once_per_customer_product(): void
    {
        [$customer, $product, $unit] = $this->prepareCustomerProduct();
        $this->seed(FoundationPermissionSeeder::class);
        $user = $this->createAdminUser();

        $group = PriceList::where('code', 'wholesale_price')->firstOrFail();
        $customerList = PriceList::where('code', 'customer_price')->firstOrFail();

        $categoryRule = $this->createRule($group, $product, $unit, '1300.0000', 200, [
            'transaction_category_id' => $customer->transaction_category_id,
        ]);
        $customerRule = $this->createRule($customerList, $product, $unit, '1200.0000', 100, [
            'customer_id' => $customer->id,
        ]);

        $categoryRule->unit_price = '1400.0000';
        $categoryRule->save();

        app(CreatePriceReviewTasksService::class)->createForChangedRule(
            changedRule: $categoryRule,
            oldUnitPrice: '1300.0000',
            newUnitPrice: '1400.0000',
            reason: 'wholesale price revision',
        );

        $task = PriceReviewTask::query()
            ->where('customer_id', $customer->id)
            ->where('product_id', $product->id)
            ->firstOrFail();

        $this->actingAs($user)
            ->postJson('/api/v1/price-review-tasks/notify-selection', [
                'customer_id' => $customer->id,
                'product_id' => $product->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.price_review_task.id', $task->id)
            ->assertJsonPath('data.price_review_task.status', PriceReviewTask::STATUS_PENDING);

        $this->assertDatabaseHas('price_review_tasks', [
            'id' => $task->id,
            'status' => PriceReviewTask::STATUS_PENDING,
            'notified_by_user_id' => $user->id,
        ]);
        $this->assertNotNull($task->fresh()->notified_at);

        $this->actingAs($user)
            ->postJson('/api/v1/price-review-tasks/notify-selection', [
                'customer_id' => $customer->id,
                'product_id' => $product->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.price_review_task', null);

        $anotherCustomer = Customer::create([
            'customer_code' => 'PRICE-CUST-002',
            'name' => '価格確認顧客2',
            'transaction_category_id' => $customer->transaction_category_id,
            'settlement_receivable_category_id' => $customer->settlement_receivable_category_id,
            'billing_cycle_id' => $customer->billing_cycle_id,
        ]);
        $anotherCustomerRule = $this->createRule($customerList, $product, $unit, '1250.0000', 100, [
            'customer_id' => $anotherCustomer->id,
        ]);
        $anotherTask = PriceReviewTask::create([
            'product_id' => $product->id,
            'changed_price_rule_id' => $categoryRule->id,
            'affected_price_rule_id' => $anotherCustomerRule->id,
            'customer_id' => $anotherCustomer->id,
            'transaction_category_id' => $anotherCustomer->transaction_category_id,
            'old_reference_price' => '1300.0000',
            'new_reference_price' => '1400.0000',
            'current_individual_price' => '1250.0000',
            'status' => PriceReviewTask::STATUS_PENDING,
            'message' => '別取引先の価格確認',
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/price-review-tasks/notify-selection', [
                'customer_id' => $anotherCustomer->id,
                'product_id' => $product->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.price_review_task.id', $anotherTask->id);
    }

    /**
     * @return array{0: Customer, 1: Product, 2: Unit}
     */
    private function prepareCustomerProduct(): array
    {
        $this->seed([
            CustomerMasterSeeder::class,
            ProductUnitMasterSeeder::class,
            PriceMasterSeeder::class,
        ]);

        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();
        $unit = Unit::where('code', 'bottle')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'PRICE-CUST-001',
            'name' => '価格確認酒店',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'PRICE-SAKE-001',
            'product_type' => 'sake',
            'name' => '価格確認酒',
            'display_name' => '価格確認酒',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_alcohol' => true,
        ]);

        return [$customer, $product, $unit];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createRule(PriceList $priceList, Product $product, Unit $unit, string $unitPrice, int $priority, array $overrides): PriceRule
    {
        return PriceRule::create(array_merge([
            'price_list_id' => $priceList->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'unit_price' => $unitPrice,
            'priority' => $priority,
            'effective_from' => '2026-01-01',
            'rounding_method' => 'round',
        ], $overrides));
    }

    private function createAdminUser(): User
    {
        $employee = Employee::create([
            'employee_code' => 'PRICEEMP'.str_pad((string) (Employee::count() + 1), 3, '0', STR_PAD_LEFT),
            'name' => 'Price Review Employee',
            'email' => 'price-review-employee'.(Employee::count() + 1).'@example.com',
        ]);

        $user = User::create([
            'employee_id' => $employee->id,
            'name' => 'Price Review User',
            'email' => 'price-review-user'.(User::count() + 1).'@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail());

        return $user;
    }
}

