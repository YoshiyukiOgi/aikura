<?php

namespace App\Services\Inventory;

use App\Models\NonSalesStockOperationHeader;
use App\Models\NonSalesStockOperationRevision;
use Illuminate\Support\Facades\Auth;

class RecordNonSalesStockOperationRevisionService
{
    public function record(NonSalesStockOperationHeader $operation, string $action, ?string $revisionReason = null): NonSalesStockOperationRevision
    {
        $operation->loadMissing(['lines.productionLot', 'lines.stockLocation']);

        return $operation->revisions()->create([
            'revision_no' => $operation->revision_no,
            'action' => $action,
            'operation_type' => $operation->operation_type,
            'operation_date' => $operation->operation_date,
            'reason' => $revisionReason ?? $operation->reason,
            'note' => $operation->note,
            'lines' => $operation->lines->map(fn ($line): array => [
                'line_no' => $line->line_no,
                'production_lot_id' => $line->production_lot_id,
                'lot_code' => $line->lot_code,
                'lot_name' => $line->productionLot?->display_name,
                'stock_location_id' => $line->stock_location_id,
                'stock_location_name' => $line->stockLocation?->name,
                'unit_id' => $line->unit_id,
                'quantity' => $line->quantity,
                'stock_movement_id' => $line->stock_movement_id,
            ])->values()->all(),
            'created_by_user_id' => Auth::id(),
        ]);
    }

    public function ensureInitialRevision(NonSalesStockOperationHeader $operation): void
    {
        if (! $operation->revisions()->exists()) {
            $this->record($operation, 'created');
        }
    }
}
