<?php

namespace App\Services\Billing;

use App\Exceptions\Billing\InvoiceReportExportException;
use App\Models\InvoiceHeader;
use App\Models\ReportExport;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateInvoiceReportService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly Filesystem $filesystem,
    ) {
    }

    public function generate(InvoiceHeader $invoice, string $format = 'txt', ?string $reason = null): ReportExport
    {
        $format = strtolower($format);

        if ($format !== 'txt') {
            throw InvoiceReportExportException::unsupportedFormat($format);
        }

        return DB::transaction(function () use ($invoice, $format, $reason): ReportExport {
            $invoice = InvoiceHeader::query()
                ->with(['customer', 'billingCycle', 'lines'])
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            if ($invoice->status !== 'confirmed') {
                throw InvoiceReportExportException::notConfirmed($invoice->id, $invoice->status);
            }

            $content = $this->renderText($invoice);
            $relativePath = $this->relativePath($invoice, $format);
            $absolutePath = storage_path('app/'.$relativePath);
            $directory = dirname($absolutePath);

            $this->filesystem->ensureDirectoryExists($directory);

            if ($this->filesystem->put($absolutePath, $content) === false) {
                throw InvoiceReportExportException::writeFailed($relativePath);
            }

            $export = ReportExport::create([
                'report_type' => 'invoice',
                'format' => $format,
                'status' => 'generated',
                'exportable_type' => $invoice::class,
                'exportable_id' => $invoice->id,
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
                event: 'invoice_report.generated',
                auditable: $invoice,
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

    private function renderText(InvoiceHeader $invoice): string
    {
        $lines = [
            'Invoice Report',
            'Invoice Number: '.$invoice->invoice_number,
            'Status: '.$invoice->status,
            'Customer: '.$invoice->customer->name,
            'Invoice Date: '.$invoice->invoice_date?->toDateString(),
            'Due Date: '.$invoice->due_date?->toDateString(),
            'Subtotal: '.$invoice->subtotal_amount,
            'Tax: '.$invoice->tax_amount,
            'Total: '.$invoice->total_amount,
            '',
            'Lines:',
        ];

        foreach ($invoice->lines as $line) {
            $lines[] = implode("\t", [
                $line->line_no,
                $line->product_code,
                $line->display_name,
                $line->quantity,
                $line->unit_name,
                $line->unit_price,
                $line->amount,
                $line->tax_amount,
                $line->total_amount,
            ]);
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function relativePath(InvoiceHeader $invoice, string $format): string
    {
        $invoiceNumber = $invoice->invoice_number ?: 'invoice-'.$invoice->id;
        $safeInvoiceNumber = Str::slug($invoiceNumber);
        $timestamp = now()->format('YmdHis');
        $issueId = Str::lower(Str::random(8));

        return "reports/invoices/{$invoice->invoice_date->format('Y')}/{$safeInvoiceNumber}-{$timestamp}-{$issueId}.{$format}";
    }
}
