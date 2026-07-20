<?php

namespace App\Services\Inventory;

use App\Exceptions\Inventory\InventoryCountException;
use App\Models\InventoryCountHeader;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use DomainException;

class ConfirmInventoryCountService
{
    public function __construct(private readonly CreateInventoryAdjustmentService $adjustmentService, private readonly AuditLogService $auditLogService) {}

    public function confirm(InventoryCountHeader $header, string $reason): InventoryCountHeader
    {
        $reason = trim($reason);
        if ($reason === '') throw new DomainException('棚卸確定理由が必要です。');
        if ($header->status !== 'draft') throw InventoryCountException::notDraft();

        return DB::transaction(function () use ($header, $reason): InventoryCountHeader {
            $header = InventoryCountHeader::query()->with('lines')->lockForUpdate()->findOrFail($header->id);
            if ($header->lines->contains(fn ($line): bool => $line->counted_quantity === null)) throw InventoryCountException::incomplete();
            if ($header->lines->contains(fn ($line): bool => bccomp((string) $line->variance_quantity, '0', 4) !== 0 && trim((string) $line->reason) === '')) throw InventoryCountException::missingVarianceReason();

            foreach ($header->lines as $line) {
                if (bccomp((string) $line->variance_quantity, '0', 4) === 0) continue;
                $movement = $this->adjustmentService->create(new CreateInventoryAdjustmentData(
                    productionLotId: $line->production_lot_id, stockLocationId: $line->stock_location_id,
                    quantity: (string) $line->variance_quantity, movementDate: $header->count_date->toDateString(), reason: (string) $line->reason,
                    lotCode: $line->lot_code,
                    sourceDocumentNumber: 'COUNT-'.sprintf('%04d%02d', $header->year, $header->month), note: $line->note,
                ));
                $line->update(['adjustment_stock_movement_id' => $movement->id]);
            }

            $header->update(['status' => 'confirmed', 'counted_at' => $header->counted_at ?? now(), 'confirmed_at' => now(), 'reason' => $reason]);
            $this->auditLogService->record(new AuditLogData(event: 'inventory_count.confirmed', auditable: $header, afterValues: ['year' => $header->year, 'month' => $header->month, 'line_count' => $header->lines->count()], reason: $reason));
            return $header->refresh()->load(['lines.stockLocation', 'lines.unit', 'lines.productionLot']);
        });
    }
}
