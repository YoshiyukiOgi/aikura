<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\FoundationPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_pages_share_workflow_tabs_in_business_order(): void
    {
        $this->seed(FoundationPermissionSeeder::class);

        $user = User::create([
            'name' => 'Billing Administrator',
            'email' => 'billing-navigation@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail());
        $this->actingAs($user);

        $tabs = [
            '/billing',
            '/billing/spot-invoices',
            '/billing/invoices',
            '/billing/invoice-print',
            '/billing/payment-entry',
            '/billing/payment-reviews',
            '/billing/receivables',
        ];

        $pages = [
            'billing.index' => '/billing',
            'billing.monthly-invoices' => '/billing',
            'billing.spot-invoices' => '/billing/spot-invoices',
            'billing.invoices' => '/billing/invoices',
            'billing.invoice-print' => '/billing/invoice-print',
            'billing.payment-confirmation' => '/billing/payment-entry',
            'billing.payment-reviews' => '/billing/payment-reviews',
            'billing.receivables' => '/billing/receivables',
        ];

        foreach ($pages as $pageRoute => $activeHref) {
            $response = $this->get(route($pageRoute));

            $response
                ->assertOk()
                ->assertSee('請求・入金業務')
                ->assertSeeInOrder($tabs)
                ->assertSee('workflow-tabs');

            $this->assertMatchesRegularExpression(
                '/href="'.preg_quote($activeHref, '/').'"[^>]*class="[^"]*\bactive\b[^"]*"/s',
                $response->getContent(),
            );
        }
    }
}
