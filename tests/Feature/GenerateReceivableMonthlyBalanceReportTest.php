<?php

namespace Tests\Feature;

use App\Exceptions\Billing\ReceivableMonthlyBalanceReportExportException;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\ReceivableMonthlyBalance;
use App\Models\SettlementReceivableCategory;
use App\Models\TransactionCategory;
use App\Services\Billing\ConfirmReceivableMonthlyBalanceService;
use App\Services\Billing\GenerateReceivableMonthlyBalanceReportService;
use Database\Seeders\CustomerMasterSeeder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateReceivableMonthlyBalanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(Filesystem::class)->deleteDirectory(storage_path('app/reports/receivable-monthly-balances'));

        parent::tearDown();
    }

    public function test_it_generates_receivable_monthly_balance_report_file_and_export_record(): void
    {
        $balances = $this->prepareConfirmedBalances();

        $export = app(GenerateReceivableMonthlyBalanceReportService::class)
            ->generate(2026, 6, reason: 'monthly receivable report');

        $absolutePath = storage_path('app/'.$export->file_path);
        $content = file_get_contents($absolutePath);

        $this->assertSame('receivable_monthly_balance', $export->report_type);
        $this->assertSame('txt', $export->format);
        $this->assertSame('generated', $export->status);
        $this->assertSame($balances->first()->id, $export->exportable_id);
        $this->assertSame('text/plain', $export->mime_type);
        $this->assertFileExists($absolutePath);
        $this->assertStringContainsString('Receivable Monthly Balance Report', $content);
        $this->assertStringContainsString('Period: 2026-06-01 - 2026-06-30', $content);
        $this->assertStringContainsString('Total Scheduled Amount: 4950.00', $content);
        $this->assertStringContainsString('Total Received Amount: 1000.00', $content);
        $this->assertStringContainsString('Total Outstanding Amount: 3950.00', $content);
        $this->assertStringContainsString('AR-REPORT-CUST-001', $content);
        $this->assertSame(strlen($content), $export->file_size);
        $this->assertSame(hash('sha256', $content), $export->checksum_sha256);

        $this->assertDatabaseHas('report_exports', [
            'id' => $export->id,
            'report_type' => 'receivable_monthly_balance',
            'format' => 'txt',
            'exportable_type' => ReceivableMonthlyBalance::class,
            'exportable_id' => $balances->first()->id,
            'reason' => 'monthly receivable report',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'receivable_monthly_balance_report.generated',
            'target_table' => 'receivable_monthly_balances',
            'target_id' => '2026-06',
            'reason' => 'monthly receivable report',
        ]);
    }

    public function test_it_keeps_reissued_receivable_monthly_balance_reports_as_separate_files(): void
    {
        $this->prepareConfirmedBalances();

        $first = app(GenerateReceivableMonthlyBalanceReportService::class)
            ->generate(2026, 6, reason: 'first receivable monthly balance report');
        $second = app(GenerateReceivableMonthlyBalanceReportService::class)
            ->generate(2026, 6, reason: 'second receivable monthly balance report');

        $this->assertNotSame($first->id, $second->id);
        $this->assertNotSame($first->file_path, $second->file_path);
        $this->assertFileExists(storage_path('app/'.$first->file_path));
        $this->assertFileExists(storage_path('app/'.$second->file_path));

        $this->assertDatabaseHas('report_exports', [
            'id' => $first->id,
            'report_type' => 'receivable_monthly_balance',
            'reason' => 'first receivable monthly balance report',
        ]);
        $this->assertDatabaseHas('report_exports', [
            'id' => $second->id,
            'report_type' => 'receivable_monthly_balance',
            'reason' => 'second receivable monthly balance report',
        ]);
    }

    public function test_it_rejects_unconfirmed_monthly_balance_report_generation(): void
    {
        $customer = $this->createCustomer('AR-DRAFT-REPORT-CUST-001');
        $this->createDraftBalance($customer, '1650.00', '0.00', '1650.00');

        $this->expectException(ReceivableMonthlyBalanceReportExportException::class);

        app(GenerateReceivableMonthlyBalanceReportService::class)->generate(2026, 6);
    }

    public function test_it_rejects_missing_monthly_balances(): void
    {
        $this->expectException(ReceivableMonthlyBalanceReportExportException::class);

        app(GenerateReceivableMonthlyBalanceReportService::class)->generate(2026, 6);
    }

    public function test_it_rejects_unsupported_report_format(): void
    {
        $this->prepareConfirmedBalances();

        $this->expectException(ReceivableMonthlyBalanceReportExportException::class);

        app(GenerateReceivableMonthlyBalanceReportService::class)->generate(2026, 6, 'pdf');
    }

    /**
     * @return \Illuminate\Support\Collection<int, ReceivableMonthlyBalance>
     */
    private function prepareConfirmedBalances(): \Illuminate\Support\Collection
    {
        $firstCustomer = $this->createCustomer('AR-REPORT-CUST-001');
        $secondCustomer = $this->createCustomer('AR-REPORT-CUST-002');

        $this->createDraftBalance($firstCustomer, '3300.00', '1000.00', '2300.00');
        $this->createDraftBalance($secondCustomer, '1650.00', '0.00', '1650.00');

        return app(ConfirmReceivableMonthlyBalanceService::class)
            ->confirm(2026, 6, 'monthly receivable confirmation');
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
