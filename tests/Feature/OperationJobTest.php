<?php

namespace Tests\Feature;

use App\Exceptions\Operations\OperationJobException;
use App\Jobs\RunMonthlyAggregationJob;
use App\Jobs\RunMonthlyClosingJob;
use App\Jobs\RunReportExportRetentionCheckJob;
use App\Jobs\RunReportGenerationJob;
use App\Models\OperationJob;
use App\Models\Product;
use App\Models\StockLocation;
use App\Models\StockMonthlyBalance;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\Operations\OperationJobService;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\StockLocationSeeder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(Filesystem::class)->deleteDirectory(storage_path('app/reports/inventory'));

        parent::tearDown();
    }

    public function test_report_generation_job_runs_service_and_records_operation_job(): void
    {
        [$product, $unit, $location] = $this->prepareStockMovement();

        dispatch_sync(new RunReportGenerationJob(
            reportType: 'stock_balance',
            reason: 'stock report job',
        ));

        $this->assertDatabaseHas('report_exports', [
            'report_type' => 'stock_balance',
            'reason' => 'stock report job',
        ]);
        $this->assertDatabaseHas('operation_jobs', [
            'job_type' => 'report.generate',
            'status' => 'completed',
            'target_type' => 'stock_balance',
            'target_id' => 'current',
            'reason' => 'stock report job',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'operation_job.started',
            'target_table' => 'operation_jobs',
            'reason' => 'stock report job',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'operation_job.completed',
            'target_table' => 'operation_jobs',
            'reason' => 'stock report job',
        ]);

        $this->assertNotNull($product->id);
        $this->assertNotNull($unit->id);
        $this->assertNotNull($location->id);
    }

    public function test_monthly_aggregation_job_creates_stock_monthly_balance_draft(): void
    {
        [$product, $unit, $location] = $this->prepareStockMovement();

        dispatch_sync(new RunMonthlyAggregationJob(
            aggregationType: 'stock_monthly_balance',
            year: 2026,
            month: 6,
            reason: 'stock aggregation job',
        ));

        $this->assertDatabaseHas('stock_monthly_balances', [
            'status' => 'draft',
            'year' => 2026,
            'month' => 6,
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'closing_quantity' => '10.0000',
            'reason' => 'stock aggregation job',
        ]);
        $this->assertDatabaseHas('operation_jobs', [
            'job_type' => 'monthly_aggregation.create',
            'status' => 'completed',
            'target_type' => 'stock_monthly_balance',
            'target_id' => '2026-06',
        ]);
    }

    public function test_monthly_closing_job_confirms_stock_monthly_balance(): void
    {
        $this->prepareStockMovement();

        dispatch_sync(new RunMonthlyAggregationJob(
            aggregationType: 'stock_monthly_balance',
            year: 2026,
            month: 6,
            reason: 'stock aggregation job',
        ));
        dispatch_sync(new RunMonthlyClosingJob(
            closingType: 'stock_monthly_balance_confirm',
            year: 2026,
            month: 6,
            reason: 'stock closing job',
        ));

        $this->assertTrue(StockMonthlyBalance::query()
            ->where('year', 2026)
            ->where('month', 6)
            ->where('status', 'confirmed')
            ->exists());
        $this->assertTrue(StockMovement::query()
            ->where('movement_date', '2026-06-15')
            ->where('status', 'closed')
            ->exists());
        $this->assertDatabaseHas('operation_jobs', [
            'job_type' => 'monthly_closing.execute',
            'status' => 'completed',
            'target_type' => 'stock_monthly_balance_confirm',
            'target_id' => '2026-06',
            'reason' => 'stock closing job',
        ]);
    }

    public function test_failed_operation_job_records_error_message(): void
    {
        $this->expectException(OperationJobException::class);

        try {
            dispatch_sync(new RunReportGenerationJob(
                reportType: 'unsupported_report',
                reason: 'unsupported report job',
            ));
        } finally {
            $job = OperationJob::query()->latest('id')->firstOrFail();

            $this->assertSame('report.generate', $job->job_type);
            $this->assertSame('failed', $job->status);
            $this->assertSame('unsupported_report', $job->target_type);
            $this->assertSame('unsupported report job', $job->reason);
            $this->assertNotNull($job->failed_at);
            $this->assertStringContainsString('Unsupported report generation job type', $job->error_message);
            $this->assertDatabaseHas('audit_logs', [
                'event' => 'operation_job.failed',
                'target_table' => 'operation_jobs',
                'target_id' => (string) $job->id,
                'reason' => 'unsupported report job',
            ]);
        }
    }

    public function test_running_duplicate_operation_job_is_rejected_and_audited(): void
    {
        $service = app(OperationJobService::class);

        $this->expectException(OperationJobException::class);

        try {
            $service->run(
                jobType: 'manual.test',
                targetType: 'target',
                targetId: '1',
                payload: [],
                reason: 'outer job',
                callback: fn () => $service->run(
                    jobType: 'manual.test',
                    targetType: 'target',
                    targetId: '1',
                    payload: [],
                    reason: 'inner duplicate job',
                    callback: fn (): string => 'never',
                ),
            );
        } finally {
            $outer = OperationJob::query()->where('job_type', 'manual.test')->firstOrFail();

            $this->assertSame('failed', $outer->status);
            $this->assertDatabaseHas('audit_logs', [
                'event' => 'operation_job.duplicate_rejected',
                'target_table' => 'operation_jobs',
                'target_id' => (string) $outer->id,
                'reason' => 'inner duplicate job',
            ]);
        }
    }

    public function test_failed_operation_job_can_be_retried_with_retry_source(): void
    {
        try {
            dispatch_sync(new RunReportGenerationJob(
                reportType: 'unsupported_report',
                reason: 'retry source job',
            ));
        } catch (OperationJobException) {
            // Expected failure creates the retry source.
        }

        $failed = OperationJob::query()->where('status', 'failed')->firstOrFail();

        app(OperationJobService::class)->retry(
            $failed,
            fn (): string => 'retried',
        );

        $retried = OperationJob::query()
            ->where('retry_of_operation_job_id', $failed->id)
            ->firstOrFail();

        $this->assertSame('completed', $retried->status);
        $this->assertSame(2, $retried->attempts);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'operation_job.retried',
            'target_table' => 'operation_jobs',
            'target_id' => (string) $retried->id,
            'reason' => 'retry source job',
        ]);
    }

    public function test_report_retention_check_job_verifies_existing_report_exports(): void
    {
        $this->prepareStockMovement();
        dispatch_sync(new RunReportGenerationJob(
            reportType: 'stock_balance',
            reason: 'retention source report',
        ));

        dispatch_sync(new RunReportExportRetentionCheckJob(
            reportType: 'stock_balance',
            reason: 'retention check job',
        ));

        $this->assertDatabaseHas('operation_jobs', [
            'job_type' => 'report_exports.retention_check',
            'status' => 'completed',
            'target_type' => 'stock_balance',
            'target_id' => 'stock_balance',
            'reason' => 'retention check job',
        ]);
    }

    public function test_operation_console_commands_can_run_jobs_synchronously(): void
    {
        $this->prepareStockMovement();

        $this->artisan('aikura:report', [
            'type' => 'stock_balance',
            '--reason' => 'console stock report',
            '--sync' => true,
        ])->assertExitCode(0);

        $this->artisan('aikura:report-retention-check', [
            '--type' => 'stock_balance',
            '--reason' => 'console retention check',
            '--sync' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('operation_jobs', [
            'job_type' => 'report.generate',
            'status' => 'completed',
            'reason' => 'console stock report',
        ]);
        $this->assertDatabaseHas('operation_jobs', [
            'job_type' => 'report_exports.retention_check',
            'status' => 'completed',
            'reason' => 'console retention check',
        ]);
    }

    /**
     * @return array{0: Product, 1: Unit, 2: StockLocation}
     */
    private function prepareStockMovement(): array
    {
        $this->seed([
            ProductUnitMasterSeeder::class,
            StockLocationSeeder::class,
        ]);

        $unit = Unit::where('code', 'bottle')->firstOrFail();
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();
        $product = Product::create([
            'product_code' => 'OPERATION-JOB-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Operation Job Sake',
            'display_name' => 'Operation Job Sake 720ml',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_alcohol' => true,
            'is_inventory_managed' => true,
        ]);

        StockMovement::create([
            'status' => 'confirmed',
            'movement_type' => 'inventory_adjustment',
            'movement_date' => '2026-06-15',
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'quantity' => '10.0000',
            'confirmed_at' => now(),
        ]);

        return [$product, $unit, $location];
    }
}
