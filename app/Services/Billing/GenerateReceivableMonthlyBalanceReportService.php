<?php

namespace App\Services\Billing;

use App\Exceptions\Billing\ReceivableMonthlyBalanceReportExportException;
use App\Models\ReceivableMonthlyBalance;
use App\Models\ReportExport;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class GenerateReceivableMonthlyBalanceReportService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly Filesystem $filesystem,
    ) {
    }

    public function generate(int $year, int $month, string $format = 'txt', ?string $reason = null): ReportExport
    {
        $format = strtolower($format);

        if ($format !== 'txt') {
            throw ReceivableMonthlyBalanceReportExportException::unsupportedFormat($format);
        }

        return DB::transaction(function () use ($year, $month, $format, $reason): ReportExport {
            $balances = ReceivableMonthlyBalance::query()
                ->where('year', $year)
                ->where('month', $month)
                ->orderBy('customer_code')
                ->lockForUpdate()
                ->get();

            if ($balances->isEmpty()) {
                throw ReceivableMonthlyBalanceReportExportException::noBalances($year, $month);
            }

            if ($balances->contains(fn (ReceivableMonthlyBalance $balance): bool => ! in_array($balance->status, ['confirmed', 'closed'], true))) {
                throw ReceivableMonthlyBalanceReportExportException::notConfirmed($year, $month);
            }

            $content = $this->renderText($balances);
            $relativePath = $this->relativePath($year, $month, $format);
            $absolutePath = storage_path('app/'.$relativePath);
            $directory = dirname($absolutePath);

            $this->filesystem->ensureDirectoryExists($directory);

            if ($this->filesystem->put($absolutePath, $content) === false) {
                throw ReceivableMonthlyBalanceReportExportException::writeFailed($relativePath);
            }

            $firstBalance = $balances->firstOrFail();
            $export = ReportExport::create([
                'report_type' => 'receivable_monthly_balance',
                'format' => $format,
                'status' => 'generated',
                'exportable_type' => $firstBalance::class,
                'exportable_id' => $firstBalance->id,
                'disk' => 'local',
                'file_path' => $relativePath,
                'file_name' => basename($relativePath),
                'mime_type' => 'text/plain',
                'file_size' => strlen($content),
                'checksum_sha256' => hash('sha256', $content),
                'generated_at' => now(),
                'reason' => $reason,
            ]);

            $this->auditLogService->record(new AuditLogData(
                event: 'receivable_monthly_balance_report.generated',
                targetTable: 'receivable_monthly_balances',
                targetId: "{$year}-".str_pad((string) $month, 2, '0', STR_PAD_LEFT),
                afterValues: [
                    'report_export_id' => $export->id,
                    'format' => $export->format,
                    'file_path' => $export->file_path,
                    'checksum_sha256' => $export->checksum_sha256,
                    'balance_count' => $balances->count(),
                ],
                reason: $reason,
            ));

            return $export->refresh();
        });
    }

    /**
     * @param Collection<int, ReceivableMonthlyBalance> $balances
     */
    private function renderText(Collection $balances): string
    {
        $firstBalance = $balances->firstOrFail();
        $lines = [
            'Receivable Monthly Balance Report',
            'Period: '.$firstBalance->period_start->toDateString().' - '.$firstBalance->period_end->toDateString(),
            'Status: '.$firstBalance->status,
            'Total Scheduled Amount: '.$this->sum($balances, 'scheduled_amount'),
            'Total Received Amount: '.$this->sum($balances, 'received_amount'),
            'Total Outstanding Amount: '.$this->sum($balances, 'outstanding_amount'),
            'Customer Count: '.$balances->count(),
            '',
            'Lines:',
        ];

        foreach ($balances as $balance) {
            $lines[] = implode("\t", [
                $balance->customer_code,
                $balance->customer_name,
                $balance->scheduled_amount,
                $balance->received_amount,
                $balance->outstanding_amount,
                $balance->open_schedule_count,
                $balance->partial_schedule_count,
                $balance->closed_schedule_count,
            ]);
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    /**
     * @param Collection<int, ReceivableMonthlyBalance> $balances
     */
    private function sum(Collection $balances, string $column): string
    {
        return $balances->reduce(
            fn (string $carry, ReceivableMonthlyBalance $balance): string => bcadd($carry, (string) $balance->{$column}, 2),
            '0.00',
        );
    }

    private function relativePath(int $year, int $month, string $format): string
    {
        $period = sprintf('%04d%02d', $year, $month);
        $timestamp = now()->format('YmdHis');
        $issueId = Str::lower(Str::random(8));

        return "reports/receivable-monthly-balances/{$year}/receivable-monthly-balance-{$period}-{$timestamp}-{$issueId}.{$format}";
    }
}
