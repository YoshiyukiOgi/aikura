<?php

namespace App\Services\Inventory;

use App\Exceptions\Shipment\ShipmentConfirmationException;
use App\Models\ShipmentHeader;
use App\Models\ShipmentLotAllocation;
use App\Models\StockMovement;

class CreateShipmentStockMovementsService
{
    public function __construct(
        private readonly EnsureStockPeriodIsOpenService $ensureStockPeriodIsOpenService,
    ) {}

    public function createForConfirmedShipment(ShipmentHeader $shipment): void
    {
        $shipment->loadMissing([
            'settlementReceivableCategory',
            'lines.product',
            'lines.unit',
            'lines.lotAllocations.productionLot',
        ]);

        if (! $shipment->settlementReceivableCategory->reduces_stock) {
            return;
        }

        foreach ($shipment->lines as $line) {
            if (! $line->product->is_inventory_managed) {
                continue;
            }

            $movementDate = ($shipment->actual_shipment_date ?? $shipment->document_date)->toDateString();
            $this->ensureStockPeriodIsOpenService->ensureOpen($movementDate);

            $allocations = $line->lotAllocations
                ->where('status', 'allocated')
                ->whereNull('cancelled_at')
                ->values();

            $allocatedQuantity = $allocations->reduce(
                fn (string $carry, ShipmentLotAllocation $allocation): string => bcadd($carry, (string) $allocation->quantity, 4),
                '0.0000',
            );

            if (bccomp($allocatedQuantity, (string) $line->quantity, 4) !== 0) {
                throw ShipmentConfirmationException::lineLotAllocationIncomplete(
                    $line->id,
                    (string) $line->quantity,
                    $allocatedQuantity,
                );
            }

            foreach ($allocations as $allocation) {
                StockMovement::create([
                    'status' => 'confirmed',
                    'movement_type' => 'shipment',
                    'movement_date' => $movementDate,
                    'stock_location_id' => $allocation->stock_location_id,
                    'unit_id' => $line->unit_id,
                    'quantity' => bcmul((string) $allocation->quantity, '-1', 4),
                    'source_type' => 'shipment',
                    'source_document_number' => $shipment->document_number,
                    'source_line_no' => $line->line_no,
                    'source_shipment_header_id' => $shipment->id,
                    'source_shipment_line_id' => $line->id,
                    'production_lot_id' => $allocation->production_lot_id,
                    'lot_code' => $allocation->productionLot?->lot_code,
                    'confirmed_at' => now(),
                ]);

                $allocation->update([
                    'status' => 'confirmed',
                    'confirmed_at' => now(),
                ]);
            }

        }
    }
}
