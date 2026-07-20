<?php

namespace App\Services\Inventory;

use App\Exceptions\Inventory\InventoryReportExportException;
use App\Models\ReportExport;
use App\Models\StockMovement;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateLotStockBalanceReportService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly LotStockBalanceService $lotStockBalanceService,
        private readonly Filesystem $filesystem,
    ) {
    }

    public function generate(string $format = 'txt', ?string $reason = null): ReportExport
    {
        $format = strtolower($format);

        if ($format !== 'txt') {
            throw InventoryReportExportException::unsupportedFormat($format);
        }

        return DB::transaction(function () use ($format, $reason): ReportExport {
            $balances = $this->lotStockBalanceService->all();

            if ($balances->isEmpty()) {
                throw InventoryReportExportException::noLotStockBalances();
            }

            $content = $this->renderText($balances);
            $relativePath = $this->relativePath($format);
            $absolutePath = storage_path('app/'.$relativePath);
            $directory = dirname($absolutePath);

            $this->filesystem->ensureDirectoryExists($directory);

            if ($this->filesystem->put($absolutePath, $content) === false) {
                throw InventoryReportExportException::writeFailed($relativePath);
            }

            $sourceMovement = StockMovement::query()
                ->whereNotNull('production_lot_id')
                ->whereIn('status', ['confirmed', 'closed'])
                ->whereNull('cancelled_at')
                ->orderBy('id')
                ->firstOrFail();

            $export = ReportExport::create([
                'report_type' => 'lot_stock_balance',
                'format' => $format,
                'status' => 'generated',
                'exportable_type' => $sourceMovement::class,
                'exportable_id' => $sourceMovement->id,
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
                event: 'lot_stock_balance_report.generated',
                targetTable: 'stock_movements',
                targetId: 'current-lot',
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
     * @param Collection<int, LotStockBalance> $balances
     */
    private function renderText(Collection $balances): string
    {
        $lines = [
            'Lot Stock Balance Report',
            'Generated At: '.now()->toIso8601String(),
            'Balance Count: '.$balances->count(),
            'Total Physical Quantity: '.$this->sum($balances, 'physicalQuantity'),
            'Total Reserved Quantity: '.$this->sum($balances, 'reservedQuantity'),
            'Total Allocated Quantity: '.$this->sum($balances, 'allocatedQuantity'),
            'Total Available Quantity: '.$this->sum($balances, 'availableQuantity'),
            '',
            'Lines:',
        ];

        foreach ($balances as $balance) {
            $lines[] = implode("\t", [
                $balance->productionLotId,
                $balance->stockLocationId,
                $balance->unitId,
                $balance->physicalQuantity,
                $balance->reservedQuantity,
                $balance->allocatedQuantity,
                $balance->availableQuantity,
            ]);
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    /**
     * @param Collection<int, LotStockBalance> $balances
     */
    private function sum(Collection $balances, string $property): string
    {
        return $balances->reduce(
            fn (string $carry, LotStockBalance $balance): string => bcadd($carry, $balance->{$property}, 4),
            '0.0000',
        );
    }

    private function relativePath(string $format): string
    {
        $timestamp = now()->format('YmdHis');
        $issueId = Str::lower(Str::random(8));

        return "reports/inventory/lot-stock-balances/lot-stock-balance-{$timestamp}-{$issueId}.{$format}";
    }
}
