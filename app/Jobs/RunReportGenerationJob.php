<?php

namespace App\Jobs;

use App\Exceptions\Operations\OperationJobException;
use App\Models\ConsumptionTaxMonthlyFiling;
use App\Models\InvoiceHeader;
use App\Models\LiquorTaxMonthlyFiling;
use App\Models\ReportExport;
use App\Models\ShipmentHeader;
use App\Services\Billing\GenerateInvoiceReportService;
use App\Services\Billing\GenerateReceivableMonthlyBalanceReportService;
use App\Services\Inventory\GenerateLotStockBalanceReportService;
use App\Services\Operations\OperationJobService;
use App\Services\Shipment\GenerateShipmentReportService;
use App\Services\Tax\GenerateConsumptionTaxFilingReportService;
use App\Services\Tax\GenerateLiquorTaxFilingReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunReportGenerationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly string $reportType,
        private readonly array $payload = [],
        private readonly string $format = 'txt',
        private readonly ?string $reason = null,
    ) {
    }

    public function handle(
        OperationJobService $operationJobService,
        GenerateShipmentReportService $shipmentReportService,
        GenerateInvoiceReportService $invoiceReportService,
        GenerateLiquorTaxFilingReportService $liquorTaxFilingReportService,
        GenerateConsumptionTaxFilingReportService $consumptionTaxFilingReportService,
        GenerateReceivableMonthlyBalanceReportService $receivableMonthlyBalanceReportService,
        GenerateLotStockBalanceReportService $lotStockBalanceReportService,
    ): ReportExport {
        return $operationJobService->run(
            jobType: 'report.generate',
            targetType: $this->reportType,
            targetId: $this->targetId(),
            payload: [
                'report_type' => $this->reportType,
                'format' => $this->format,
                'payload' => $this->payload,
            ],
            reason: $this->reason,
            callback: fn (): ReportExport => match ($this->reportType) {
                'shipment' => $shipmentReportService->generate(
                    ShipmentHeader::findOrFail((int) $this->payload['shipment_id']),
                    $this->format,
                    $this->reason,
                ),
                'invoice' => $invoiceReportService->generate(
                    InvoiceHeader::findOrFail((int) $this->payload['invoice_id']),
                    $this->format,
                    $this->reason,
                ),
                'liquor_tax_filing' => $liquorTaxFilingReportService->generate(
                    LiquorTaxMonthlyFiling::findOrFail((int) $this->payload['filing_id']),
                    $this->format,
                    $this->reason,
                ),
                'consumption_tax_filing' => $consumptionTaxFilingReportService->generate(
                    ConsumptionTaxMonthlyFiling::findOrFail((int) $this->payload['filing_id']),
                    $this->format,
                    $this->reason,
                ),
                'receivable_monthly_balance' => $receivableMonthlyBalanceReportService->generate(
                    (int) $this->payload['year'],
                    (int) $this->payload['month'],
                    $this->format,
                    $this->reason,
                ),
                'stock_balance' => $lotStockBalanceReportService->generate($this->format, $this->reason),
                'lot_stock_balance' => $lotStockBalanceReportService->generate($this->format, $this->reason),
                default => throw OperationJobException::unsupportedReportType($this->reportType),
            },
        );
    }

    private function targetId(): ?string
    {
        return match ($this->reportType) {
            'shipment' => isset($this->payload['shipment_id']) ? (string) $this->payload['shipment_id'] : null,
            'invoice' => isset($this->payload['invoice_id']) ? (string) $this->payload['invoice_id'] : null,
            'liquor_tax_filing', 'consumption_tax_filing' => isset($this->payload['filing_id'])
                ? (string) $this->payload['filing_id']
                : null,
            'receivable_monthly_balance' => isset($this->payload['year'], $this->payload['month'])
                ? sprintf('%04d-%02d', (int) $this->payload['year'], (int) $this->payload['month'])
                : null,
            'stock_balance' => 'current',
            'lot_stock_balance' => 'current-lot',
            default => null,
        };
    }
}
