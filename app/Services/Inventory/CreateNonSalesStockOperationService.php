<?php

namespace App\Services\Inventory;

use App\Exceptions\Inventory\NonSalesStockOperationException;
use App\Models\NonSalesStockOperationHeader;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use App\Services\NumberSequence\NumberSequenceService;
use Illuminate\Support\Facades\DB;

class CreateNonSalesStockOperationService
{
    public function __construct(
        private readonly NumberSequenceService $numberSequenceService,
        private readonly AuditLogService $auditLogService,
        private readonly EnsureStockPeriodIsOpenService $ensureStockPeriodIsOpenService,
        private readonly ReplaceNonSalesStockOperationLinesService $replaceLinesService,
        private readonly RecordNonSalesStockOperationRevisionService $revisionService,
        private readonly EvaluateRepackagingAlcoholWarningService $repackagingAlcoholService,
    ) {
    }

    public function create(CreateNonSalesStockOperationData $data): NonSalesStockOperationHeader
    {
        $reason = trim($data->reason);
        if ($reason === '' && ! in_array($data->operationType, ['bottling', 'repackaging'], true)) {
            throw NonSalesStockOperationException::emptyReason();
        }
        if ($data->lines === []) {
            throw NonSalesStockOperationException::emptyLines();
        }
        if ($data->operationType === 'repackaging') {
            $this->repackagingAlcoholService->ensureAcknowledged($data->lines, $data->alcoholWarningAcknowledged);
        }

        $this->ensureStockPeriodIsOpenService->ensureOpen($data->operationDate);

        return DB::transaction(function () use ($data, $reason): NonSalesStockOperationHeader {
            $header = NonSalesStockOperationHeader::create([
                'operation_number' => $this->numberSequenceService->next('non_sales_stock_operation')->formatted,
                'status' => 'confirmed',
                'operation_type' => $data->operationType,
                'operation_date' => $data->operationDate,
                'source_sales_return_header_id' => $data->sourceSalesReturnHeaderId,
                'confirmed_at' => now(),
                'reason' => $reason,
                'note' => $data->note,
            ]);

            $this->replaceLinesService->replace($header, $data->operationType, $data->operationDate, $reason, $data->lines);
            $this->revisionService->record($header->refresh()->load(['lines.productionLot', 'lines.stockLocation']), 'created');
            $this->auditLogService->record(new AuditLogData(
                event: 'non_sales_stock_operation.confirmed',
                auditable: $header,
                afterValues: [
                    'operation_number' => $header->operation_number,
                    'operation_type' => $header->operation_type,
                    'operation_date' => $header->operation_date?->toDateString(),
                    'line_count' => $header->lines()->count(),
                ],
                reason: $reason,
            ));

            return $header->refresh()->load(['lines.productionLot', 'lines.stockLocation']);
        });
    }
}
