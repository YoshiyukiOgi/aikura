<?php

namespace App\Services\Inventory;

use App\Models\StockMovement;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use DomainException;
use Illuminate\Support\Facades\DB;

class ReverseStockMovementService
{
    public function __construct(private readonly EnsureStockPeriodIsOpenService $ensureOpen, private readonly AuditLogService $auditLogService) {}

    public function reverse(StockMovement $original, string $movementDate, string $reason): StockMovement
    {
        $reason = trim($reason);
        if ($reason === '') throw new DomainException('訂正理由が必要です。');
        if ($original->status === 'cancelled' || $original->cancelled_at) throw new DomainException('取消済みの在庫移動です。');
        if (StockMovement::query()->where('related_stock_movement_id', $original->id)->where('movement_type', 'stock_correction')->whereNull('cancelled_at')->exists()) throw new DomainException('この在庫移動は訂正済みです。');
        $this->ensureOpen->ensureOpen($movementDate);

        return DB::transaction(function () use ($original, $movementDate, $reason): StockMovement {
            $reversal = StockMovement::create([
                'status' => 'confirmed', 'movement_type' => 'stock_correction', 'movement_date' => $movementDate,
                'stock_location_id' => $original->stock_location_id, 'unit_id' => $original->unit_id,
                'quantity' => bcmul((string) $original->quantity, '-1', 4), 'source_type' => 'stock_correction',
                'source_document_number' => $original->source_document_number, 'source_line_no' => $original->source_line_no,
                'related_stock_movement_id' => $original->id, 'production_lot_id' => $original->production_lot_id, 'lot_code' => $original->lot_code,
                'confirmed_at' => now(), 'reason' => $reason,
            ]);
            $this->auditLogService->record(new AuditLogData(event: 'stock_movement.corrected', auditable: $reversal, afterValues: ['original_id' => $original->id, 'quantity' => $reversal->quantity, 'movement_date' => $movementDate], reason: $reason));
            return $reversal->refresh();
        });
    }
}
