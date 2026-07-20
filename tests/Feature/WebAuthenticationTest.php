<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\FoundationPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_and_admin_can_open_sales_order_page(): void
    {
        $this->seed(FoundationPermissionSeeder::class);
        $user = User::create([
            'name' => 'Sales Administrator',
            'email' => 'admin@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail());

        $this->get('/sales-orders')->assertRedirect(route('login'));

        $this->withSession(['_token' => 'test-token'])->post('/login', [
            '_token' => 'test-token',
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertRedirect(route('sales-orders.index'));

        $this->get('/sales-orders')
            ->assertOk()
            ->assertSee('受注一覧')
            ->assertSee('Sales Administrator');

        $this->getJson('/api/v1/sales-orders')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);
    }

    public function test_user_without_sales_order_view_permission_is_forbidden_from_page(): void
    {
        $user = User::create([
            'name' => 'Limited User',
            'email' => 'limited@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/sales-orders')
            ->assertForbidden();
    }

    public function test_inactive_user_cannot_sign_in(): void
    {
        $user = User::create([
            'name' => 'Inactive User',
            'email' => 'inactive@example.test',
            'password' => 'password',
            'is_active' => false,
        ]);

        $this->withSession(['_token' => 'test-token'])->post('/login', [
            '_token' => 'test-token',
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
