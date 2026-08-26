<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_tax_page_allows_initial_draft_creation_when_no_filing_exists(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->createUser('tax-page@example.com');
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail());

        $this->actingAs($user)
            ->get('/tax')
            ->assertOk()
            ->assertSee("if(selected&&selected.status!=='draft')", false)
            ->assertDontSee("if(selected?.status!=='draft')", false);
    }

    private function createUser(string $email): User
    {
        $employee = Employee::create([
            'employee_code' => 'TAXPAGE'.str_pad((string) (Employee::count() + 1), 3, '0', STR_PAD_LEFT),
            'name' => 'Tax Page User',
            'email' => 'employee-'.$email,
        ]);

        return User::create([
            'employee_id' => $employee->id,
            'name' => 'Tax Page User',
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);
    }
}
