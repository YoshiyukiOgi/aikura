<?php

namespace Tests\Feature;

use App\Exceptions\Billing\ReceivableMonthlyBalanceCloseException;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\ReceivableMonthlyBalance;
use App\Models\SettlementReceivableCategory;
use App\Models\TransactionCategory;
use App\Services\Billing\CloseReceivableMonthlyBalanceService;
use App\Services\Billing\ConfirmReceivableMonthlyBalanceService;
use App\Services\Billing\GenerateReceivableMonthlyBalanceReportService;
use Database\Seeders\CustomerMasterSeeder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CloseReceivableMonthlyBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(Filesystem::class)->deleteDirectory(storage_path('app/reports/receivable-monthly-balances'));

        parent::tearDown();
    }

    public function test_it_closes_confirmed_receivable_monthly_balances(): void
    {
        $customer = $this->createCustomer('AR-CLOSE-CUST-001');
        $this->createDraftBalance($customer, '3300.00', '1000.00', '2300.00');
        app(ConfirmReceivableMonthlyBalanceService::class)->confirm(2026, 6, 'monthly receivable confirmation');

        $balances = app(CloseReceivableMonthlyBalanceService::class)
            ->close(2026, 6, 'monthly receivable close');

        $this->assertCount(1, $balances);
        $this->assertSame('closed', $balances->first()->status);
        $this->assertNotNull($balances->first()->confirmed_at);
        $this->assertNotNull($balances->first()->closed_at);
        $this->assertSame('monthly receivable close', $balances->first()->reason);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'receivable_monthly_balance.closed',
            'target_table' => 'receivable_monthly_balances',
            'target_id' => '2026-06',
            'reason' => 'monthly receivable close',
        ]);
    }

    public function test_it_rejects_close_without_confirmed_balances(): void
    {
        $this->expectException(ReceivableMonthlyBalanceCloseException::class);

        app(CloseReceivableMonthlyBalanceService::class)->close(2026, 6, 'monthly receivable close');
    }

    public function test_it_rejects_empty_reason(): void
    {
        $customer = $this->createCustomer('AR-CLOSE-EMPTY-REASON-CUST-001');
        $this->createDraftBalance($customer, '1650.00', '0.00', '1650.00');
        app(ConfirmReceivableMonthlyBalanceService::class)->confirm(2026, 6, 'monthly receivable confirmation');

        $this->expectException(ReceivableMonthlyBalanceCloseException::class);

        app(CloseReceivableMonthlyBalanceService::class)->close(2026, 6, ' ');
    }

    public function test_it_rejects_draft_month(): void
    {
        $customer = $this->createCustomer('AR-CLOSE-DRAFT-CUST-001');
        $this->createDraftBalance($customer, '1650.00', '0.00', '1650.00');

        $this->expectException(ReceivableMonthlyBalanceCloseException::class);

        app(CloseReceivableMonthlyBalanceService::class)->close(2026, 6, 'monthly receivable close');
    }

    public function test_it_rejects_already_closed_month(): void
    {
        $customer = $this->createCustomer('AR-CLOSE-ALREADY-CUST-001');
        $this->createDraftBalance($customer, '1650.00', '0.00', '1650.00');
        app(ConfirmReceivableMonthlyBalanceService::class)->confirm(2026, 6, 'monthly receivable confirmation');
        app(CloseReceivableMonthlyBalanceService::class)->close(2026, 6, 'monthly receivable close');

        $this->expectException(ReceivableMonthlyBalanceCloseException::class);

        app(CloseReceivableMonthlyBalanceService::class)->close(2026, 6, 'second close');
    }

    public function test_closed_receivable_monthly_balance_can_be_reported(): void
    {
        $customer = $this->createCustomer('AR-CLOSE-REPORT-CUST-001');
        $this->createDraftBalance($customer, '1650.00', '0.00', '1650.00');
        app(ConfirmReceivableMonthlyBalanceService::class)->confirm(2026, 6, 'monthly receivable confirmation');
        app(CloseReceivableMonthlyBalanceService::class)->close(2026, 6, 'monthly receivable close');

        $export = app(GenerateReceivableMonthlyBalanceReportService::class)
            ->generate(2026, 6, reason: 'report after close');

        $content = file_get_contents(storage_path('app/'.$export->file_path));

        $this->assertStringContainsString('Status: closed', $content);
        $this->assertDatabaseHas('report_exports', [
            'id' => $export->id,
            'report_type' => 'receivable_monthly_balance',
            'reason' => 'report after close',
        ]);
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
            'partial_schedule_count' => bccomp($receivedAmount, '0.00', 2) === 1 ? 1 : 0,
            'closed_schedule_count' => bccomp($outstandingAmount, '0.00', 2) === 0 ? 1 : 0,
            'calculated_at' => now(),
        ]);
    }
}
