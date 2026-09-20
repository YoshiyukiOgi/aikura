<?php

use App\Jobs\RunMonthlyAggregationJob;
use App\Jobs\RunMonthlyClosingJob;
use App\Jobs\RunReportExportRetentionCheckJob;
use App\Jobs\RunReportGenerationJob;
use App\Models\Customer;
use App\Services\Billing\RecordInternalBalanceOpeningService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('retail:detect-brewery-products')
    ->everyMinute()
    ->withoutOverlapping();

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

Artisan::command('aikura:internal-balance-opening {customer_code} {as_of_date} {amount} {--note=}', function (RecordInternalBalanceOpeningService $service): int {
    $customer = Customer::query()->where('customer_code', (string) $this->argument('customer_code'))->firstOrFail();
    $opening = $service->record(
        $customer,
        (string) $this->argument('as_of_date'),
        (string) $this->argument('amount'),
        (string) ($this->option('note') ?? '社内残高の移行開始残高'),
    );
    $this->info("recorded: {$opening->customer_id} {$opening->as_of_date->toDateString()} {$opening->opening_balance_amount}");

    return self::SUCCESS;
})->purpose('Record or correct an auditable internal-balance opening amount before internal month closing.');

Artisan::command('aikura:report-retention-check {--type=} {--reason=} {--sync}', function (): int {
    $job = new RunReportExportRetentionCheckJob(
        reportType: $this->option('type'),
        reason: $this->option('reason'),
    );

    $this->option('sync') ? dispatch_sync($job) : dispatch($job);
    $this->info($this->option('sync') ? 'completed' : 'queued');

    return self::SUCCESS;
})->purpose('Verify generated report files against report export history.');
