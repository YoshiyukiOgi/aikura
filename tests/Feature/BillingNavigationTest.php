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
            '月次請求',
            '都度請求',
            '請求一覧・確定',
            '請求書印刷',
            '入金確認',
            '要確認入金',
            '売掛残高',
        ];
        $pages = [
            'billing.index' => 'billing.monthly-invoices',
            'billing.monthly-invoices' => 'billing.monthly-invoices',
            'billing.spot-invoices' => 'billing.spot-invoices',
            'billing.invoices' => 'billing.invoices',
            'billing.invoice-print' => 'billing.invoice-print',
            'billing.payment-confirmation' => 'billing.payment-confirmation',
            'billing.payment-reviews' => 'billing.payment-reviews',
            'billing.receivables' => 'billing.receivables',
        ];

        foreach ($pages as $pageRoute => $activeRoute) {
            $response = $this->get(route($pageRoute));

            $response
                ->assertOk()
                ->assertSee('請求・入金業務')
                ->assertSeeInOrder($tabs)
                ->assertSee('aria-label="請求・入金の作業順"', false);

            $this->assertMatchesRegularExpression(
                '/href="'.preg_quote(route($activeRoute), '/').'" class="tab active"\s+aria-current="page"/',
                $response->getContent(),
            );
        }
    }
}
