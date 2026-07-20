<?php

namespace Tests\Feature;

use App\Exceptions\Billing\ReceivableMonthlyBalanceConfirmationException;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\ReceivableMonthlyBalance;
use App\Models\SettlementReceivableCategory;
use App\Models\TransactionCategory;
use App\Services\Billing\ConfirmReceivableMonthlyBalanceService;
use Database\Seeders\CustomerMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfirmReceivableMonthlyBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_confirms_receivable_monthly_balances(): void
    {
        $firstCustomer = $this->createCustomer('AR-CONFIRM-CUST-001');
        $secondCustomer = $this->createCustomer('AR-CONFIRM-CUST-002');

        $this->createDraftBalance($firstCustomer, '3300.00', '1000.00', '2300.00');
        $this->createDraftBalance($secondCustomer, '1650.00', '1650.00', '0.00');

        $balances = app(ConfirmReceivableMonthlyBalanceService::class)
            ->confirm(2026, 6, 'monthly receivable close');

        $this->assertCount(2, $balances);
        $this->assertTrue($balances->every(fn (ReceivableMonthlyBalance $balance): bool => $balance->status === 'confirmed'));
        $this->assertTrue($balances->every(fn (ReceivableMonthlyBalance $balance): bool => $balance->confirmed_at !== null));
        $this->assertTrue($balances->every(fn (ReceivableMonthlyBalance $balance): bool => $balance->reason === 'monthly receivable close'));

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'receivable_monthly_balance.confirmed',
            'target_table' => 'receivable_monthly_balances',
            'target_id' => '2026-06',
            'reason' => 'monthly receivable close',
        ]);
    }

    public function test_it_rejects_confirmation_without_draft_balances(): void
    {
        $this->expectException(ReceivableMonthlyBalanceConfirmationException::class);

        app(ConfirmReceivableMonthlyBalanceService::class)->confirm(2026, 6, 'monthly receivable close');
    }

    public function test_it_rejects_empty_reason(): void
    {
        $customer = $this->createCustomer('AR-EMPTY-REASON-CUST-001');
        $this->createDraftBalance($customer, '1650.00', '0.00', '1650.00');

        $this->expectException(ReceivableMonthlyBalanceConfirmationException::class);

        app(ConfirmReceivableMonthlyBalanceService::class)->confirm(2026, 6, ' ');
    }

    public function test_it_rejects_already_confirmed_month(): void
    {
        $customer = $this->createCustomer('AR-ALREADY-CONFIRMED-CUST-001');
        $balance = $this->createDraftBalance($customer, '1650.00', '0.00', '1650.00');
        $balance->update(['status' => 'confirmed']);

        $this->expectException(ReceivableMonthlyBalanceConfirmationException::class);

        app(ConfirmReceivableMonthlyBalanceService::class)->confirm(2026, 6, 'monthly receivable close');
    }

    private function createCustomer(string $customerCode): Customer
    {
        $this->seed(CustomerMasterSeeder::class);

        return Customer::create([
            'customer_code' => $customerCode,
            'name' => $customerCode.' Name',
            'transaction_category_id' => TransactionCategory::where('code', 'wholesale')->firstOrFail()->id,
            'settlement_receivable_category_id' => SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail()->id,
            'billing_cycle_id' => BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail()->id,
        ]);
    }

    private function createDraftBalance(
        Customer $customer,
        string $scheduledAmount,
        string $receivedAmount,
        string $outstandingAmount,
    ): ReceivableMonthlyBalance {
        return ReceivableMonthlyBalance::create([
            'status' => 'draft',
            'year' => 2026,
            'month' => 6,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'customer_id' => $customer->id,
            'customer_code' => $customer->customer_code,
            'customer_name' => $customer->name,
            'scheduled_amount' => $scheduledAmount,
            'received_amount' => $receivedAmount,
            'outstanding_amount' => $outstandingAmount,
            'open_schedule_count' => bccomp($outstandingAmount, '0.00', 2) === 1 ? 1 : 0,
            'partial_schedule_count' => 0,
            'closed_schedule_count' => bccomp($outstandingAmount, '0.00', 2) === 0 ? 1 : 0,
            'calculated_at' => now(),
        ]);
    }
}
