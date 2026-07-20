<?php

namespace App\Services\Inventory;

use App\Models\ShipmentHeader;
use App\Models\StockMovement;

class ReverseShipmentStockMovementsService
{
    public function __construct(
        private readonly EnsureStockPeriodIsOpenService $ensureStockPeriodIsOpenService,
    ) {
    }

    public function reverseForCancelledShipment(ShipmentHeader $shipment, string $reason): void
    {
        $movements = StockMovement::query()
            ->where('source_shipment_header_id', $shipment->id)
            ->where('movement_type', 'shipment')
            ->whereIn('status', ['confirmed', 'closed'])
            ->whereNull('cancelled_at')
            ->lockForUpdate()
            ->get();

        foreach ($movements as $movement) {
            $movementDate = $movement->movement_date->toDateString();
            $this->ensureStockPeriodIsOpenService->ensureOpen($movementDate);

            $exists = StockMovement::query()
                ->where('related_stock_movement_id', $movement->id)
                ->where('movement_type', 'shipment_cancellation')
                ->exists();

            if ($exists) {
                continue;
            }

            StockMovement::create([
                'status' => 'confirmed',
                'movement_type' => 'shipment_cancellation',
                'movement_date' => $movementDate,
                'stock_location_id' => $movement->stock_location_id,
                'unit_id' => $movement->unit_id,
                'quantity' => bcmul((string) $movement->quantity, '-1', 4),
                'source_type' => 'shipment_cancellation',
                'source_document_number' => $shipment->document_number,
                'source_line_no' => $movement->source_line_no,
                'source_shipment_header_id' => $shipment->id,
                'source_shipment_line_id' => $movement->source_shipment_line_id,
                'related_stock_movement_id' => $movement->id,
                'production_lot_id' => $movement->production_lot_id,
                'lot_code' => $movement->lot_code,
                'confirmed_at' => now(),
                'reason' => $reason,
            ]);
        }
    }
}
