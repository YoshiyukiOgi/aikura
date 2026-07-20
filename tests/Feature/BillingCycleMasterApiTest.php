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

class BillingCycleMasterApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_edit_an_unused_billing_cycle(): void
    {
        $this->actingAsAdmin();
        $payload = $this->payload();

        $created = $this->postJson('/api/v1/masters/billing-cycles', $payload)
            ->assertCreated()
            ->assertJsonPath('data.billing_cycle.closing_day', 20)
            ->assertJsonPath('data.billing_cycle.payment_day', 31);
        $id = $created->json('data.billing_cycle.id');

        $payload['closing_day'] = 15;
        $payload['name'] = '15日締め翌月末入金';
        $payload['change_reason'] = '締日の見直し';
        $this->putJson("/api/v1/masters/billing-cycles/{$id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.billing_cycle.closing_day', 15);

        $this->get('/masters/billing-cycles')->assertOk()->assertSee('締日条件マスター');
        $this->getJson("/api/v1/masters/billing-cycles/{$id}")
            ->assertOk()
            ->assertJsonPath('data.billing_cycle.history.0.reason', '締日の見直し');
        $this->assertSame(2, AuditLog::query()->where('target_table', 'billing_cycles')->where('target_id', (string) $id)->count());
    }

    public function test_used_cycle_requires_a_new_record_when_calculation_fields_change(): void
    {
        $this->actingAsAdmin();
        $cycle = BillingCycle::query()->where('code', 'monthly_end_next_month_end')->firstOrFail();
        $this->createCustomer($cycle);
        $payload = $this->payload([
            'name' => '変更後の条件',
            'closing_day' => 20,
            'change_reason' => '締日変更',
        ]);

        $this->putJson("/api/v1/masters/billing-cycles/{$cycle->id}", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('billing_method');

        $payload['closing_day'] = 31;
        $payload['name'] = '月末締め翌月末入金（名称変更）';
        $this->putJson("/api/v1/masters/billing-cycles/{$cycle->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.billing_cycle.is_calculation_locked', true);

        unset($payload['change_reason']);
        $payload['closing_day'] = 20;
        $payload['name'] = '20日締め翌月末入金';
        $this->postJson('/api/v1/masters/billing-cycles', $payload)
            ->assertCreated()
            ->assertJsonPath('data.billing_cycle.is_calculation_locked', false);
    }

    public function test_duplicate_calculation_condition_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/masters/billing-cycles', $this->payload())->assertCreated();
        $this->postJson('/api/v1/masters/billing-cycles', $this->payload(['name' => '重複条件']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_user_without_permission_cannot_view_or_edit_billing_cycles(): void
    {
        $this->seed([FoundationPermissionSeeder::class, CustomerMasterSeeder::class]);
        $user = User::query()->create([
            'name' => 'Read only',
            'email' => 'billing-cycle-readonly@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get('/masters/billing-cycles')->assertForbidden();
        $this->actingAs($user)->getJson('/api/v1/masters/billing-cycles')->assertForbidden();
        $this->actingAs($user)->postJson('/api/v1/masters/billing-cycles', $this->payload())->assertForbidden();
    }

    private function actingAsAdmin(): User
    {
        $this->seed([FoundationPermissionSeeder::class, CustomerMasterSeeder::class]);
        $user = User::query()->create([
            'name' => 'Master Administrator',
            'email' => 'billing-cycle-admin@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::query()->where('code', 'admin')->firstOrFail());
        $this->actingAs($user);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return [
            'name' => '20日締め翌月末入金',
            'billing_method' => 'monthly_closing',
            'closing_day' => 20,
            'payment_month_offset' => 1,
            'payment_day' => 31,
            'description' => 'テスト用条件',
            'is_active' => true,
            ...$overrides,
        ];
    }

    private function createCustomer(BillingCycle $cycle): Customer
    {
        return Customer::query()->create([
            'customer_code' => 'CYCLE-C-001',
            'name' => '締日条件利用中取引先',
            'transaction_category_id' => TransactionCategory::query()->where('code', 'wholesale')->firstOrFail()->id,
            'settlement_receivable_category_id' => SettlementReceivableCategory::query()->where('code', 'accounts_receivable_1')->firstOrFail()->id,
            'billing_cycle_id' => $cycle->id,
            'tax_rounding_method' => 'ceil',
            'tax_calculation_unit' => 'invoice',
            'amount_rounding_method' => 'round',
            'invoice_required' => true,
            'is_active' => true,
        ]);
    }
}
