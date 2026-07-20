<?php

namespace App\Services\Shipment;

use App\Exceptions\Shipment\ShipmentReportExportException;
use App\Models\ReportExport;
use App\Models\ShipmentHeader;
use App\Models\ShipmentLine;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use App\Services\Tax\TaxRoundingService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateShipmentReportService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly Filesystem $filesystem,
        private readonly TaxRoundingService $taxRoundingService,
    ) {
    }

    public function generate(ShipmentHeader $shipment, string $format = 'txt', ?string $reason = null): ReportExport
    {
        $format = strtolower($format);

        if ($format !== 'txt') {
            throw ShipmentReportExportException::unsupportedFormat($format);
        }

        return DB::transaction(function () use ($shipment, $format, $reason): ReportExport {
            $shipment = ShipmentHeader::query()
                ->with(['customer', 'transactionCategory', 'settlementReceivableCategory', 'billingCycle', 'lines'])
                ->lockForUpdate()
                ->findOrFail($shipment->id);

            if ($shipment->status !== 'confirmed') {
                throw ShipmentReportExportException::notConfirmed($shipment->id, $shipment->status);
            }

            $content = $this->renderText($shipment);
            $relativePath = $this->relativePath($shipment, $format);
            $absolutePath = storage_path('app/'.$relativePath);
            $directory = dirname($absolutePath);

            $this->filesystem->ensureDirectoryExists($directory);

            if ($this->filesystem->put($absolutePath, $content) === false) {
                throw ShipmentReportExportException::writeFailed($relativePath);
            }

            $export = ReportExport::create([
                'report_type' => 'shipment',
                'format' => $format,
                'status' => 'generated',
                'exportable_type' => $shipment::class,
                'exportable_id' => $shipment->id,
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
                event: 'shipment_report.generated',
                auditable: $shipment,
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

    private function renderText(ShipmentHeader $shipment): string
    {
        $lines = [
            'Shipment Report',
            'Document Number: '.$shipment->document_number,
            'Status: '.$shipment->status,
            'Customer: '.$shipment->customer->name,
            'Document Date: '.$shipment->document_date?->toDateString(),
            'Actual Shipment Date: '.$shipment->actual_shipment_date?->toDateString(),
            'Billing Target Date: '.$shipment->billing_target_date?->toDateString(),
            'Liquor Tax Transfer Date: '.$shipment->liquor_tax_transfer_date?->toDateString(),
            'Transaction Category: '.$shipment->transactionCategory->name,
            '',
            'Lines:',
        ];

        $subtotal = '0.00';
        $consumptionTax = '0.00';
        $liquorTaxEstimate = '0.00';

        foreach ($shipment->lines as $line) {
            $lineAmount = $this->lineAmount($line);
            $lineConsumptionTax = $this->lineConsumptionTax($line, $lineAmount);
            $lineLiquorTax = bcadd((string) ($line->confirmed_liquor_tax_estimated_amount ?? '0.00'), '0', 2);
            $subtotal = bcadd($subtotal, $lineAmount, 2);
            $consumptionTax = bcadd($consumptionTax, $lineConsumptionTax, 2);
            $liquorTaxEstimate = bcadd($liquorTaxEstimate, $lineLiquorTax, 2);

            $lines[] = implode("\t", [
                $line->line_no,
                $line->confirmed_product_code,
                $line->confirmed_display_name,
                $line->confirmed_quantity,
                $line->confirmed_unit_name,
                $line->confirmed_unit_price,
                $lineAmount,
                $line->confirmed_consumption_tax_category_code,
                $line->confirmed_consumption_tax_rate,
                $lineConsumptionTax,
                $line->confirmed_liquor_tax_category_code,
                $line->confirmed_liquor_taxable_kl,
                $lineLiquorTax,
            ]);
        }

        $lines[] = '';
        $lines[] = 'Subtotal: '.$subtotal;
        $lines[] = 'Consumption Tax: '.$consumptionTax;
        $lines[] = 'Liquor Tax Estimate: '.$liquorTaxEstimate;
        $lines[] = 'Grand Total: '.bcadd($subtotal, $consumptionTax, 2);

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function lineAmount(ShipmentLine $line): string
    {
        $quantity = bcadd((string) $line->confirmed_quantity, '0', 4);
        $unitPrice = bcadd((string) $line->confirmed_unit_price, '0', 4);

        return bcmul($quantity, $unitPrice, 2);
    }

    private function lineConsumptionTax(ShipmentLine $line, string $lineAmount): string
    {
        if ($line->confirmed_consumption_tax_rate === null || $line->confirmed_consumption_taxability !== 'taxable') {
            return '0.00';
        }

        $rawTax = bcmul($lineAmount, (string) $line->confirmed_consumption_tax_rate, 4);

        return $this->taxRoundingService->round($rawTax, $line->confirmed_rounding_method ?? 'floor', 2);
    }

    private function relativePath(ShipmentHeader $shipment, string $format): string
    {
        $documentNumber = $shipment->document_number ?: 'shipment-'.$shipment->id;
        $safeDocumentNumber = Str::slug($documentNumber);
        $timestamp = now()->format('YmdHis');

        return "reports/shipments/{$shipment->document_date->format('Y')}/{$safeDocumentNumber}-{$timestamp}.{$format}";
    }
}
