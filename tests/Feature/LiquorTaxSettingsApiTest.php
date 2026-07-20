<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiquorTaxSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_schedule_new_scheme_and_close_legacy_period(): void
    {
        $user = $this->admin();

        $this->actingAs($user)->postJson('/api/v1/tax/liquor-settings/relief-periods', [
            'scheme' => 'new_scheme',
            'effective_from' => '2027-04-01',
            'opening_gross_tax_amount' => '0',
            'prior_year_total_taxable_quantity_kl' => '120.000000',
            'prior_year_peak_taxable_quantity_kl' => '80.000000',
            'approval_date' => '2027-03-15',
            'approval_reference' => 'APPROVAL-2027-001',
            'discontinuance_notice_date' => '2027-03-20',
            'reason' => '2027年度から新制度へ移行',
        ])->assertCreated()
            ->assertJsonPath('data.liquor_tax_relief_setting.scheme', 'new_scheme');

        $this->assertDatabaseHas('liquor_tax_relief_settings', [
            'scheme' => 'legacy_scheme',
            'effective_to' => '2027-03-31',
        ]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'liquor_tax_relief_setting.created']);
    }

    public function test_new_scheme_cannot_be_changed_back_to_legacy(): void
    {
        $user = $this->admin();
        $this->actingAs($user)->postJson('/api/v1/tax/liquor-settings/relief-periods', $this->newSchemePayload())
            ->assertCreated();

        $this->actingAs($user)->postJson('/api/v1/tax/liquor-settings/relief-periods', [
            'scheme' => 'legacy_scheme',
            'effective_from' => '2028-04-01',
            'reason' => '旧制度へ戻す',
        ])->assertStatus(409);
    }

    public function test_admin_can_update_adjustment_approval_thresholds(): void
    {
        $user = $this->admin();

        $this->actingAs($user)->putJson('/api/v1/tax/liquor-settings/adjustment-thresholds', [
            'approval_amount_threshold' => '50000',
            'approval_quantity_threshold_kl' => '0.100000',
            'is_active' => true,
            'reason' => '承認基準を設定',
        ])->assertOk()
            ->assertJsonPath('data.liquor_tax_adjustment_setting.approval_amount_threshold', '50000.00');
    }

    public function test_user_without_permission_cannot_change_settings(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->user('limited-settings@example.com');

        $this->actingAs($user)->postJson('/api/v1/tax/liquor-settings/relief-periods', $this->newSchemePayload())
            ->assertForbidden();
    }

    private function newSchemePayload(): array
    {
        return [
            'scheme' => 'new_scheme',
            'effective_from' => '2027-04-01',
            'prior_year_total_taxable_quantity_kl' => '120.000000',
            'prior_year_peak_taxable_quantity_kl' => '80.000000',
            'approval_date' => '2027-03-15',
            'approval_reference' => 'APPROVAL-2027-001',
            'reason' => '新制度へ移行',
        ];
    }

    private function admin(): User
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->user('liquor-settings-admin@example.com');
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail());

        return $user;
    }

    private function user(string $email): User
    {
        $employee = Employee::create([
            'employee_code' => 'LTSET'.str_pad((string) (Employee::count() + 1), 3, '0', STR_PAD_LEFT),
            'name' => 'Liquor Tax Settings User',
            'email' => 'employee-'.$email,
        ]);

        return User::create([
            'employee_id' => $employee->id,
            'name' => 'Liquor Tax Settings User',
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);
    }
}
