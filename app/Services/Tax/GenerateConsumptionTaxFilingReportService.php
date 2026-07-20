<?php

namespace App\Services\Tax;

use App\Exceptions\Tax\ConsumptionTaxFilingReportExportException;
use App\Models\ConsumptionTaxMonthlyFiling;
use App\Models\ReportExport;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateConsumptionTaxFilingReportService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly Filesystem $filesystem,
    ) {
    }

    public function generate(ConsumptionTaxMonthlyFiling $filing, string $format = 'txt', ?string $reason = null): ReportExport
    {
        $format = strtolower($format);

        if ($format !== 'txt') {
            throw ConsumptionTaxFilingReportExportException::unsupportedFormat($format);
        }

        return DB::transaction(function () use ($filing, $format, $reason): ReportExport {
            $filing = ConsumptionTaxMonthlyFiling::query()
                ->with('lines')
                ->lockForUpdate()
                ->findOrFail($filing->id);

            if ($filing->status !== 'confirmed') {
                throw ConsumptionTaxFilingReportExportException::notConfirmed($filing->id, $filing->status);
            }

            $content = $this->renderText($filing);
            $relativePath = $this->relativePath($filing, $format);
            $absolutePath = storage_path('app/'.$relativePath);
            $directory = dirname($absolutePath);

            $this->filesystem->ensureDirectoryExists($directory);

            if ($this->filesystem->put($absolutePath, $content) === false) {
                throw ConsumptionTaxFilingReportExportException::writeFailed($relativePath);
            }

            $export = ReportExport::create([
                'report_type' => 'consumption_tax_filing',
                'format' => $format,
                'status' => 'generated',
                'exportable_type' => $filing::class,
                'exportable_id' => $filing->id,
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
                event: 'consumption_tax_filing_report.generated',
                auditable: $filing,
                afterValues: [
                    'report_export_id' => $export->id,
                    'format' => $export->format,
                    'file_path' => $export->file_path,
                    'checksum_sha256' => $export->checksum_sha256,
                ],
                reason: $reason,
            ));

            return $export->refresh();
        });
    }

    private function renderText(ConsumptionTaxMonthlyFiling $filing): string
    {
        $lines = [
            'Consumption Tax Monthly Filing Report',
            'Period: '.$filing->period_start->toDateString().' - '.$filing->period_end->toDateString(),
            'Status: '.$filing->status,
            'Total Taxable Amount: '.$filing->total_taxable_amount,
            'Total Confirmed Tax Amount: '.$filing->total_confirmed_tax_amount,
            'Total Amount: '.$filing->total_amount,
            'Invoice Count: '.$filing->invoice_count,
            'Line Count: '.$filing->line_count,
            '',
            'Lines:',
        ];

        foreach ($filing->lines as $line) {
            $lines[] = implode("\t", [
                $line->line_no,
                $line->consumption_tax_category_code,
                $line->consumption_tax_category_name,
                $line->consumption_taxability,
                $line->tax_rate,
                $line->consumption_tax_rate_effective_from?->toDateString(),
                $line->taxable_amount,
                $line->confirmed_tax_amount,
                $line->total_amount,
                $line->invoice_count,
                $line->line_count,
            ]);
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function relativePath(ConsumptionTaxMonthlyFiling $filing, string $format): string
    {
        $period = $filing->period_start->format('Ym');
        $timestamp = now()->format('YmdHis');
        $issueId = Str::lower(Str::random(8));

        return "reports/consumption-tax-filings/{$filing->year}/consumption-tax-filing-{$period}-{$timestamp}-{$issueId}.{$format}";
    }
}
