<?php

namespace App\Console\Commands;

use App\Services\Billing\ImportLegacyReceivableClosings;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportLegacyReceivableClosingsCommand extends Command
{
    protected $signature = 'aikura:access-close-receivables
        {batch : Access migration batch ID}
        {statement : Resolved paper statement CSV}
        {--year=2026 : Statement year}
        {--month=5 : Statement month}
        {--through=2026-06 : Last month to project from Access history}
        {--expected-total= : Expected paper statement total}
        {--statement-complete : Confirm that all receivable statement categories are included}
        {--apply : Persist closed monthly balances}';

    protected $description = 'Import a paper receivable closing balance and project later Access months.';

    public function handle(ImportLegacyReceivableClosings $importer): int
    {
        $year = (int) $this->option('year');
        $month = (int) $this->option('month');
        $apply = (bool) $this->option('apply');

        if ($apply && ! $this->option('statement-complete')) {
            $this->error('Refusing to close receivables without --statement-complete. Partial paper statements may only be dry-run.');

            return self::FAILURE;
        }

        try {
            DB::beginTransaction();
            $summaries = [$importer->importStatement(
                path: (string) $this->argument('statement'),
                year: $year,
                month: $month,
                expectedTotal: $this->option('expected-total') ?: null,
                apply: true,
            )];

            $cursor = CarbonImmutable::create($year, $month, 1)->addMonth();
            $through = CarbonImmutable::createFromFormat('!Y-m', (string) $this->option('through'));
            while ($cursor->lessThanOrEqualTo($through)) {
                $summaries[] = $importer->projectMonthFromAccess(
                    accessMigrationBatchId: (int) $this->argument('batch'),
                    year: $cursor->year,
                    month: $cursor->month,
                    apply: true,
                );
                $cursor = $cursor->addMonth();
            }

            if ($apply) {
                DB::commit();
            } else {
                DB::rollBack();
            }
            foreach ($summaries as &$summary) {
                $summary['applied'] = $apply;
            }
            unset($summary);
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Month', 'Rows', 'Shipments', 'Shipment total', 'Receipts/fees', 'Closing total', 'Negatives', 'Mode'],
            collect($summaries)->map(fn (array $summary): array => [
                sprintf('%04d-%02d', $summary['year'], $summary['month']),
                $summary['row_count'],
                $summary['shipment_count'] ?? '-',
                $summary['shipment_total'] ?? '-',
                $summary['receipts_and_fees_total'] ?? '-',
                $summary['total'],
                $summary['negative_count'] ?? '-',
                $summary['applied'] ? 'applied' : 'dry-run',
            ])->all(),
        );

        $negativeRows = collect($summaries)->flatMap(fn (array $summary): array => $summary['negative_rows'] ?? []);
        if ($negativeRows->isNotEmpty()) {
            $this->warn('Negative closing balances require review:');
            $this->table(
                ['Customer', 'Name', 'Opening', 'Sales', 'Receipts/fees', 'Closing'],
                $negativeRows->map(fn (array $row): array => [
                    $row['customer_code'],
                    $row['customer_name'],
                    $row['opening'],
                    $row['sales'],
                    $row['receipts'],
                    $row['outstanding'],
                ])->all(),
            );
        }

        return self::SUCCESS;
    }
}
