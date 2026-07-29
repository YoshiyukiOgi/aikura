<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Role;
use App\Models\SettlementReceivableCategory;
use App\Models\TransactionCategory;
use App\Models\User;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\FoundationPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingPaymentPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_list_print_uses_the_current_filters_and_totals(): void
    {
        $this->seed([FoundationPermissionSeeder::class, CustomerMasterSeeder::class]);

        $user = User::create([
            'name' => 'Payment Print Admin',
            'email' => 'payment-print@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail());
        $this->actingAs($user);

        $customer = Customer::create([
            'customer_code' => 'PAYMENT-PRINT-CUST',
            'name' => '入金印刷テスト商店',
            'transaction_category_id' => TransactionCategory::where('code', 'wholesale')->firstOrFail()->id,
            'settlement_receivable_category_id' => SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail()->id,
            'billing_cycle_id' => BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail()->id,
        ]);

        Payment::create([
            'customer_id' => $customer->id,
            'status' => 'review_required',
            'payment_date' => '2026-07-02',
            'payment_method' => 'bank_transfer',
            'amount' => '34836.00',
            'unapplied_amount' => '1200.00',
            'reference_number' => 'PAY-PRINT-001',
        ]);
        Payment::create([
            'customer_id' => $customer->id,
            'status' => 'allocated',
            'payment_date' => '2026-07-03',
            'payment_method' => 'bank_transfer',
            'amount' => '5000.00',
            'unapplied_amount' => '0.00',
            'reference_number' => 'FILTERED-OUT',
        ]);

        $this->get(route('billing.payments.print', [
            'customer' => '入金印刷',
            'payment_date_from' => '2026-07-01',
            'payment_date_to' => '2026-07-31',
            'status' => 'review_required',
            'has_unapplied' => 1,
        ]))
            ->assertOk()
            ->assertSee('入金一覧')
            ->assertSee('入金印刷テスト商店')
            ->assertSee('2026/07/02')
            ->assertSee('¥34,836')
            ->assertSee('¥1,200')
            ->assertSee('PAY-PRINT-001')
            ->assertSee('1件')
            ->assertDontSee('FILTERED-OUT');
    }

    public function test_payment_review_page_has_a_print_button(): void
    {
        $this->seed(FoundationPermissionSeeder::class);

        $user = User::create([
            'name' => 'Payment Review Admin',
            'email' => 'payment-review-print@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail());

        $this->actingAs($user)
            ->get(route('billing.payment-reviews'))
            ->assertOk()
            ->assertSee('id="payment-review-print"', false)
            ->assertSee('/billing/payments/print', false);
    }
}
