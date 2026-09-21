<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\FoundationPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiPermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_permission_protected_api(): void
    {
        $this->getJson('/api/v1/audit-logs')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_user_without_permission_cannot_access_permission_protected_api(): void
    {
        $this->actingAs($this->createUser())
            ->getJson('/api/v1/audit-logs')
            ->assertForbidden()
            ->assertJsonPath('permission', 'audit_log.view');
    }

    public function test_inactive_user_cannot_access_permission_protected_api(): void
    {
        $this->seed(FoundationPermissionSeeder::class);

        $user = $this->createUser(['is_active' => false]);
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail());

        $this->actingAs($user)
            ->getJson('/api/v1/audit-logs')
            ->assertForbidden()
            ->assertJsonPath('message', 'ユーザーが無効です。');
    }

    public function test_user_with_permission_can_access_permission_protected_api(): void
    {
        $this->seed(FoundationPermissionSeeder::class);

        $user = $this->createUser();
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail());

        $this->actingAs($user)
            ->getJson('/api/v1/audit-logs')
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createUser(array $attributes = []): User
    {
        $employee = Employee::create([
            'employee_code' => 'API'.str_pad((string) (Employee::count() + 1), 3, '0', STR_PAD_LEFT),
            'name' => 'API Test Employee',
            'email' => 'api-employee'.(Employee::count() + 1).'@example.com',
        ]);

        return User::create(array_merge([
            'employee_id' => $employee->id,
            'name' => 'API Test User',
            'email' => 'api-user'.(User::count() + 1).'@example.com',
            'password' => 'password',
            'is_active' => true,
        ], $attributes));
    }
}
