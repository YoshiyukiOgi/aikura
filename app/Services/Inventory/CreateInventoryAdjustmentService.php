<?php

namespace App\Services\Inventory;

use App\Exceptions\Inventory\InventoryAdjustmentException;
use App\Models\ProductionLot;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

class CreateInventoryAdjustmentService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly EnsureStockPeriodIsOpenService $ensureStockPeriodIsOpenService,
    ) {
    }

    public function create(CreateInventoryAdjustmentData $data): StockMovement
    {
        $reason = trim($data->reason);

        if ($reason === '') {
            throw InventoryAdjustmentException::emptyReason();
        }

        if (bccomp($data->quantity, '0', 4) === 0) {
            throw InventoryAdjustmentException::zeroQuantity();
        }

        $this->ensureStockPeriodIsOpenService->ensureOpen($data->movementDate);

        return DB::transaction(function () use ($data, $reason): StockMovement {
            $productionLot = ProductionLot::query()->lockForUpdate()->findOrFail($data->productionLotId);
            $stockLocation = StockLocation::query()->lockForUpdate()->findOrFail($data->stockLocationId);

            if (! $productionLot->is_active || $productionLot->status !== 'active') {
                throw new \DomainException('使用できないロットです。');
            }
            if ($productionLot->unit_id === null) {
                throw new \DomainException('ロットの在庫単位が設定されていません。');
            }

            if (! $stockLocation->is_active || ! $stockLocation->is_inventory_managed) {
                throw InventoryAdjustmentException::inactiveStockLocation($stockLocation->id);
            }

            $movement = StockMovement::create([
                'status' => 'confirmed',
                'movement_type' => 'inventory_adjustment',
                'movement_date' => $data->movementDate,
                'stock_location_id' => $stockLocation->id,
                'unit_id' => $productionLot->unit_id,
                'quantity' => bcadd($data->quantity, '0', 4),
                'source_type' => 'inventory_adjustment',
                'source_document_number' => $data->sourceDocumentNumber,
                'production_lot_id' => $productionLot->id,
                'lot_code' => $productionLot->lot_code,
                'confirmed_at' => now(),
                'reason' => $reason,
                'note' => $data->note,
            ]);

            $this->auditLogService->record(new AuditLogData(
                event: 'stock_movement.inventory_adjusted',
                auditable: $movement,
                afterValues: [
                    'movement_type' => $movement->movement_type,
                    'movement_date' => $movement->movement_date?->toDateString(),
                    'production_lot_id' => $movement->production_lot_id,
                    'stock_location_id' => $movement->stock_location_id,
                    'unit_id' => $movement->unit_id,
                    'quantity' => $movement->quantity,
                    'source_document_number' => $movement->source_document_number,
                    'lot_code' => $movement->lot_code,
                ],
                reason: $reason,
            ));

            return $movement->refresh()->load(['productionLot', 'stockLocation', 'unit']);
        });
    }
}
