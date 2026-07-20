<?php

namespace Tests\Feature;

use App\Exceptions\Authorization\PermissionDeniedException;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use Database\Seeders\FoundationPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_foundation_permission_seeder_creates_admin_role_with_all_permissions(): void
    {
        $this->seed(FoundationPermissionSeeder::class);

        $adminRole = Role::where('code', 'admin')->firstOrFail();

        $this->assertTrue($adminRole->is_system);
        $this->assertTrue($adminRole->is_active);
        $this->assertSame(count(config('permissions.permissions')), Permission::count());
        $this->assertSame(Permission::count(), $adminRole->permissions()->count());
    }

    public function test_user_has_permission_through_active_role(): void
    {
        $this->seed(FoundationPermissionSeeder::class);

        $user = $this->createUser();
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail());

        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->hasPermission('audit_log.view'));
        $this->assertTrue(app(AuthorizationService::class)->can($user, 'audit_log.view'));
    }

    public function test_assert_can_throws_when_permission_is_missing(): void
    {
        $user = $this->createUser();

        $this->expectException(PermissionDeniedException::class);

        app(AuthorizationService::class)->assertCan($user, 'audit_log.view');
    }

    public function test_inactive_user_has_no_permissions(): void
    {
        $this->seed(FoundationPermissionSeeder::class);

        $user = $this->createUser(['is_active' => false]);
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail());

        $this->assertFalse($user->hasRole('admin'));
        $this->assertFalse($user->hasPermission('audit_log.view'));

        $this->expectException(PermissionDeniedException::class);

        app(AuthorizationService::class)->assertCan($user, 'audit_log.view');
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createUser(array $attributes = []): User
    {
        $employee = Employee::create([
            'employee_code' => 'EMP'.str_pad((string) (Employee::count() + 1), 3, '0', STR_PAD_LEFT),
            'name' => '権限 太郎',
            'email' => 'employee'.(Employee::count() + 1).'@example.com',
        ]);

        return User::create(array_merge([
            'employee_id' => $employee->id,
            'name' => '権限ユーザー',
            'email' => 'user'.(User::count() + 1).'@example.com',
            'password' => 'password',
            'is_active' => true,
        ], $attributes));
    }
}

