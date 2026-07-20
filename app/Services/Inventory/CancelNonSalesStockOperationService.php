<?php

namespace App\Services\Inventory;

use App\Models\NonSalesStockOperationHeader;
use App\Models\StockMovement;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use DomainException;
use Illuminate\Support\Facades\DB;

class CancelNonSalesStockOperationService
{
    public function __construct(private readonly EnsureStockPeriodIsOpenService $ensureOpen, private readonly RecordNonSalesStockOperationRevisionService $revisionService, private readonly AuditLogService $auditLogService) {}

    public function cancel(NonSalesStockOperationHeader $operation, string $reason): NonSalesStockOperationHeader
    {
        $reason = trim($reason);
        if ($reason === '') throw new DomainException('取消理由が必要です。');
        $this->ensureOpen->ensureOpen($operation->operation_date->toDateString());

        return DB::transaction(function () use ($operation, $reason): NonSalesStockOperationHeader {
            $operation = NonSalesStockOperationHeader::query()->with(['lines.stockMovement', 'lines.productionLot', 'lines.stockLocation'])->lockForUpdate()->findOrFail($operation->id);
            if ($operation->status !== 'confirmed') throw new DomainException('確定済みの販売外在庫出入だけを取り消せます。');
            if ($operation->source_sales_return_header_id !== null) throw new DomainException('返品処理から作成された販売外在庫出入は、この画面では取り消せません。');
            $this->revisionService->ensureInitialRevision($operation);
            $nextRevision = $operation->revision_no + 1;
            foreach ($operation->lines as $line) {
                $original = $line->stockMovement;
                if (! $original) continue;
                if (StockMovement::query()->where('related_stock_movement_id', $original->id)->exists()) throw new DomainException('既に打消処理された在庫移動を含むため取り消せません。');
                StockMovement::create([
                    'status' => 'confirmed', 'movement_type' => 'non_sales_cancellation', 'movement_date' => $original->movement_date,
                    'stock_location_id' => $original->stock_location_id, 'unit_id' => $original->unit_id,
                    'quantity' => bcmul((string) $original->quantity, '-1', 4), 'source_type' => 'non_sales_stock_operation_cancellation',
                    'source_document_number' => $operation->operation_number, 'source_line_no' => $line->line_no,
                    'related_stock_movement_id' => $original->id, 'production_lot_id' => $original->production_lot_id, 'lot_code' => $original->lot_code,
                    'confirmed_at' => now(), 'reason' => "取消打消: {$reason}",
                ]);
            }
            $operation->update(['revision_no' => $nextRevision, 'status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_reason' => $reason]);
            $operation = $operation->refresh()->load(['lines.productionLot', 'lines.stockLocation']);
            $this->revisionService->record($operation, 'cancelled', $reason);
            $this->auditLogService->record(new AuditLogData(event: 'non_sales_stock_operation.cancelled', auditable: $operation, afterValues: ['status' => 'cancelled', 'revision_no' => $nextRevision], reason: $reason));
            return $operation->load('revisions.createdBy');
        });
    }
}
