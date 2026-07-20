<?php

use App\Jobs\RunMonthlyAggregationJob;
use App\Jobs\RunMonthlyClosingJob;
use App\Jobs\RunReportExportRetentionCheckJob;
use App\Jobs\RunReportGenerationJob;
use Illuminate\Support\Facades\Artisan;

Artisan::command('aikura:health', function (): int {
    $this->info('ok');

    return self::SUCCESS;
})->purpose('Check the application console bootstrap.');

Artisan::command('aikura:report {type} {--id=} {--year=} {--month=} {--format=txt} {--reason=} {--sync}', function (): int {
    $type = (string) $this->argument('type');
    $id = $this->option('id');
    $year = $this->option('year');
    $month = $this->option('month');
    $payload = match ($type) {
        'shipment' => ['shipment_id' => (int) $id],
        'invoice' => ['invoice_id' => (int) $id],
        'liquor_tax_filing', 'consumption_tax_filing' => ['filing_id' => (int) $id],
        'receivable_monthly_balance' => ['year' => (int) $year, 'month' => (int) $month],
        'stock_balance', 'lot_stock_balance' => [],
        default => [],
    };
    $job = new RunReportGenerationJob(
        reportType: $type,
        payload: $payload,
        format: (string) $this->option('format'),
        reason: $this->option('reason'),
    );

    $this->option('sync') ? dispatch_sync($job) : dispatch($job);
    $this->info($this->option('sync') ? 'completed' : 'queued');

    return self::SUCCESS;
})->purpose('Generate a report through the operation job pipeline.');

Artisan::command('aikura:monthly-aggregation {type} {year} {month} {--reason=} {--sync}', function (): int {
    $job = new RunMonthlyAggregationJob(
        aggregationType: (string) $this->argument('type'),
        year: (int) $this->argument('year'),
        month: (int) $this->argument('month'),
        reason: $this->option('reason'),
    );

    $this->option('sync') ? dispatch_sync($job) : dispatch($job);
    $this->info($this->option('sync') ? 'completed' : 'queued');

    return self::SUCCESS;
})->purpose('Create monthly aggregation drafts through the operation job pipeline.');

Artisan::command('aikura:monthly-closing {type} {year} {month} {--reason=} {--sync}', function (): int {
    $job = new RunMonthlyClosingJob(
        closingType: (string) $this->argument('type'),
        year: (int) $this->argument('year'),
        month: (int) $this->argument('month'),
        reason: (string) $this->option('reason'),
    );

    $this->option('sync') ? dispatch_sync($job) : dispatch($job);
    $this->info($this->option('sync') ? 'completed' : 'queued');

    return self::SUCCESS;
})->purpose('Run monthly closing through the operation job pipeline.');

Artisan::command('aikura:report-retention-check {--type=} {--reason=} {--sync}', function (): int {
    $job = new RunReportExportRetentionCheckJob(
        reportType: $this->option('type'),
        reason: $this->option('reason'),
    );

    $this->option('sync') ? dispatch_sync($job) : dispatch($job);
    $this->info($this->option('sync') ? 'completed' : 'queued');

    return self::SUCCESS;
})->purpose('Verify generated report files against report export history.');
