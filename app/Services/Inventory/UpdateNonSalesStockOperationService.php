<?php

namespace App\Services\Inventory;

use App\Exceptions\Inventory\NonSalesStockOperationException;
use App\Models\NonSalesStockOperationHeader;
use App\Models\StockMovement;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use DomainException;
use Illuminate\Support\Facades\DB;

class UpdateNonSalesStockOperationService
{
    public function __construct(
        private readonly EnsureStockPeriodIsOpenService $ensureOpen,
        private readonly ReplaceNonSalesStockOperationLinesService $replaceLinesService,
        private readonly RecordNonSalesStockOperationRevisionService $revisionService,
        private readonly AuditLogService $auditLogService,
        private readonly EvaluateRepackagingAlcoholWarningService $repackagingAlcoholService,
    ) {
    }

    public function update(NonSalesStockOperationHeader $operation, CreateNonSalesStockOperationData $data): NonSalesStockOperationHeader
    {
        $reason = trim($data->reason);
        if ($reason === '') {
            throw NonSalesStockOperationException::emptyReason();
        }
        if ($data->operationType === 'repackaging') {
            $this->repackagingAlcoholService->ensureAcknowledged($data->lines, $data->alcoholWarningAcknowledged);
        }
        $this->ensureOpen->ensureOpen($operation->operation_date->toDateString());
        $this->ensureOpen->ensureOpen($data->operationDate);

        return DB::transaction(function () use ($operation, $data, $reason): NonSalesStockOperationHeader {
            $operation = NonSalesStockOperationHeader::query()->with(['lines.stockMovement', 'lines.productionLot', 'lines.stockLocation'])->lockForUpdate()->findOrFail($operation->id);
            if ($operation->status !== 'confirmed') {
                throw new DomainException('確定済みの販売外在庫出入だけを更新できます。');
            }
            if ($operation->source_sales_return_header_id !== null) {
                throw new DomainException('返品処理から作成された販売外在庫出入は、この画面では更新できません。');
            }

            $this->revisionService->ensureInitialRevision($operation);
            $before = $this->auditValues($operation);
            $nextRevision = $operation->revision_no + 1;
            foreach ($operation->lines as $line) {
                $original = $line->stockMovement;
                if (! $original) {
                    continue;
                }
                if (StockMovement::query()->where('related_stock_movement_id', $original->id)->exists()) {
                    throw new DomainException('既に打消処理された在庫移動を含むため変更できません。');
                }
                StockMovement::create([
                    'status' => 'confirmed',
                    'movement_type' => 'non_sales_revision_reversal',
                    'movement_date' => $original->movement_date,
                    'stock_location_id' => $original->stock_location_id,
                    'unit_id' => $original->unit_id,
                    'quantity' => bcmul((string) $original->quantity, '-1', 4),
                    'source_type' => 'non_sales_stock_operation_revision',
                    'source_document_number' => $operation->operation_number,
                    'source_line_no' => $line->line_no,
                    'related_stock_movement_id' => $original->id,
                    'production_lot_id' => $original->production_lot_id,
                    'lot_code' => $original->lot_code,
                    'confirmed_at' => now(),
                    'reason' => "第{$nextRevision}版への変更打消: {$reason}",
                ]);
            }

            $operation->update([
                'revision_no' => $nextRevision,
                'operation_type' => $data->operationType,
                'operation_date' => $data->operationDate,
                'reason' => $reason,
                'note' => $data->note,
                'confirmed_at' => now(),
            ]);
            $this->replaceLinesService->replace($operation, $data->operationType, $data->operationDate, $reason, $data->lines);
            $operation = $operation->refresh()->load(['lines.productionLot', 'lines.stockLocation']);
            $this->revisionService->record($operation, 'updated');
            $this->auditLogService->record(new AuditLogData(
                event: 'non_sales_stock_operation.updated',
                auditable: $operation,
                beforeValues: $before,
                afterValues: $this->auditValues($operation),
                reason: $reason,
            ));

            return $operation->load('revisions.createdBy');
        });
    }

    /** @return array<string, mixed> */
    private function auditValues(NonSalesStockOperationHeader $operation): array
    {
        return ['revision_no' => $operation->revision_no, 'operation_type' => $operation->operation_type, 'operation_date' => $operation->operation_date?->toDateString(), 'reason' => $operation->reason, 'line_count' => $operation->lines()->count()];
    }
}
