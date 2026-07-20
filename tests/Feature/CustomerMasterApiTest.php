<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\Role;
use App\Models\SettlementReceivableCategory;
use App\Models\TransactionCategory;
use App\Models\User;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\FoundationPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerMasterApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_create_update_and_disable_a_customer(): void
    {
        $this->seed([FoundationPermissionSeeder::class, CustomerMasterSeeder::class]);
        $user = User::query()->create([
            'name' => 'Master Administrator',
            'email' => 'master@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::query()->where('code', 'admin')->firstOrFail());
        $this->actingAs($user);

        $transactionCategory = TransactionCategory::query()->where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::query()->where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::query()->where('code', 'monthly_end_next_month_end')->firstOrFail();
        $payload = [
            'customer_code' => 'MASTER-C-001',
            'name' => '取引先マスターテスト酒店',
            'name_kana' => 'トリヒキサキマスターテストサケテン',
            'short_name' => 'マスター酒店',
            'billing_name' => '取引先マスターテスト酒店 御中',
            'postal_code' => '123-4567',
            'address1' => '高知県テスト市1-2-3',
            'address2' => null,
            'phone' => '088-000-0000',
            'fax' => null,
            'email' => 'customer@example.test',
            'contact_name' => '担当者',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
            'tax_rounding_method' => 'ceil',
            'tax_calculation_unit' => 'invoice',
            'amount_rounding_method' => 'round',
            'invoice_required' => true,
            'note' => '新規登録',
            'is_active' => true,
        ];

        $created = $this->postJson('/api/v1/masters/customers', $payload)
            ->assertCreated()
            ->assertJsonPath('data.customer.customer_code', 'MASTER-C-001');
        $customerId = $created->json('data.customer.id');

        $this->getJson('/api/v1/masters/customers?q=マスター酒店&active=all')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.customers.0.id', $customerId);
        $this->get('/masters/customers')->assertOk()->assertSee('取引先一覧');

        unset($payload['customer_code']);
        $payload['phone'] = '088-111-1111';
        $payload['is_active'] = false;
        $payload['change_reason'] = '電話番号変更および取引停止';
        $this->putJson("/api/v1/masters/customers/{$customerId}", $payload)
            ->assertOk()
            ->assertJsonPath('data.customer.is_active', false);

        $customer = Customer::query()->findOrFail($customerId);
        $this->assertSame('088-111-1111', $customer->phone);
        $this->assertNotNull($customer->disabled_at);
        $this->assertSame(2, AuditLog::query()->where('target_table', 'customers')->where('target_id', (string) $customerId)->count());
        $this->getJson("/api/v1/masters/customers/{$customerId}")
            ->assertOk()
            ->assertJsonPath('data.customer.history.0.reason', '電話番号変更および取引停止');
    }

    public function test_view_permission_does_not_allow_customer_edits(): void
    {
        $this->seed([FoundationPermissionSeeder::class, CustomerMasterSeeder::class]);
        $user = User::query()->create([
            'name' => 'Read only',
            'email' => 'readonly@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get('/masters/customers')->assertForbidden();
        $this->actingAs($user)->getJson('/api/v1/masters/customers')->assertForbidden();
    }
}
