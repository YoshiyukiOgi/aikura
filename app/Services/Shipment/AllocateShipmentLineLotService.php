<?php

namespace App\Services\Shipment;

use App\Exceptions\Shipment\ShipmentLotAllocationException;
use App\Models\ProductionLot;
use App\Models\ShipmentLine;
use App\Models\ShipmentLotAllocation;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\Audit\AuditLogService;
use App\Services\Inventory\EvaluateLotProductCompatibilityService;
use DomainException;
use Illuminate\Support\Facades\DB;

class AllocateShipmentLineLotService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly EvaluateLotProductCompatibilityService $compatibility,
        private readonly ApprovalService $approvalService,
    ) {
    }

    public function allocate(
        ShipmentLine $shipmentLine,
        ProductionLot $productionLot,
        StockLocation $stockLocation,
        string $quantity,
        ?string $reason = null,
        ?User $requester = null,
    ): ShipmentLotAllocation {
        return DB::transaction(function () use ($shipmentLine, $productionLot, $stockLocation, $quantity, $reason, $requester): ShipmentLotAllocation {
            $shipmentLine = ShipmentLine::query()
                ->with(['shipmentHeader', 'product', 'unit'])
                ->lockForUpdate()
                ->findOrFail($shipmentLine->id);

            $productionLot = ProductionLot::query()
                ->lockForUpdate()
                ->findOrFail($productionLot->id);

            $stockLocation = StockLocation::query()
                ->lockForUpdate()
                ->findOrFail($stockLocation->id);

            if ($shipmentLine->shipmentHeader->status !== 'draft') {
                throw ShipmentLotAllocationException::notDraft(
                    $shipmentLine->shipmentHeader->id,
                    $shipmentLine->shipmentHeader->status,
                );
            }

            $quantity = bcadd($quantity, '0', 4);

            if (bccomp($quantity, '0.0000', 4) <= 0) {
                throw ShipmentLotAllocationException::invalidQuantity($quantity);
            }

            if (! $productionLot->is_active || $productionLot->status !== 'active') {
                throw ShipmentLotAllocationException::inactiveLot($productionLot->id);
            }

            $newLineAllocatedQuantity = bcadd($this->lineAllocatedQuantity($shipmentLine), $quantity, 4);

            if (bccomp($newLineAllocatedQuantity, (string) $shipmentLine->quantity, 4) > 0) {
                throw ShipmentLotAllocationException::exceedsLineQuantity(
                    $shipmentLine->id,
                    (string) $shipmentLine->quantity,
                    $newLineAllocatedQuantity,
                );
            }

            $availableQuantity = $this->availableLotQuantity($productionLot, $stockLocation, $shipmentLine->unit_id);

            if (bccomp($quantity, $availableQuantity, 4) > 0) {
                throw ShipmentLotAllocationException::insufficientLotStock(
                    $productionLot->id,
                    $availableQuantity,
                    $quantity,
                );
            }

            $compatibility = $this->compatibility->evaluate($shipmentLine->product, $productionLot, $shipmentLine->unit_id);
            if (! $compatibility['selectable']) {
                throw new DomainException('The selected lot does not meet the product package or analysis requirements: '.$compatibility['status']);
            }
            $alcohol = $compatibility['alcohol'];

            if ($alcohol['status'] === 'out_of_range' && $this->lineAllocatedQuantity($shipmentLine) !== '0.0000') {
                throw new DomainException('An out-of-range lot must be placed on a separate shipment line.');
            }

            $allocation = ShipmentLotAllocation::create([
                'status' => 'allocated',
                'shipment_header_id' => $shipmentLine->shipment_header_id,
                'shipment_line_id' => $shipmentLine->id,
                'product_id' => $shipmentLine->product_id,
                'production_lot_id' => $productionLot->id,
                'stock_location_id' => $stockLocation->id,
                'unit_id' => $shipmentLine->unit_id,
                'quantity' => $quantity,
                'standard_alcohol_percentage' => $alcohol['standard'],
                'actual_alcohol_percentage' => $alcohol['actual'],
                'allowed_alcohol_min' => $alcohol['min'],
                'allowed_alcohol_max' => $alcohol['max'],
                'alcohol_compliance_status' => $alcohol['status'],
                'allocated_at' => now(),
                'reason' => $reason,
            ]);

            if ($alcohol['approval_required']) {
                if (! $requester || trim((string) $reason) === '') {
                    throw new DomainException('An exception reason is required to request approval for an out-of-range lot.');
                }
                $approval = $this->approvalService->request(
                    requester: $requester,
                    actionType: ApprovalService::ACTION_ALCOHOL_LOT_EXCEPTION,
                    targetType: ShipmentLotAllocation::class,
                    targetId: (string) $allocation->id,
                    reason: (string) $reason,
                    payload: [
                        'shipment_line_id' => $shipmentLine->id,
                        'product_id' => $shipmentLine->product_id,
                        'production_lot_id' => $productionLot->id,
                        'standard_alcohol_percentage' => $alcohol['standard'],
                        'actual_alcohol_percentage' => $alcohol['actual'],
                        'allowed_alcohol_min' => $alcohol['min'],
                        'allowed_alcohol_max' => $alcohol['max'],
                    ],
                );
                $allocation->update(['approval_request_id' => $approval->id]);
            }

            $this->auditLogService->recordModelChange(
                event: 'shipment_lot_allocation.allocated',
                model: $allocation,
                afterValues: $allocation->toArray(),
                reason: $reason,
            );

            return $allocation;
        });
    }

    private function availableLotQuantity(ProductionLot $productionLot, StockLocation $stockLocation, int $unitId): string
    {
        $physicalQuantity = StockMovement::query()
            ->where('production_lot_id', $productionLot->id)
            ->where('stock_location_id', $stockLocation->id)
            ->where('unit_id', $unitId)
            ->whereIn('status', ['confirmed', 'closed'])
            ->whereNull('cancelled_at')
            ->sum('quantity');

        $allocatedQuantity = ShipmentLotAllocation::query()
            ->where('production_lot_id', $productionLot->id)
            ->where('stock_location_id', $stockLocation->id)
            ->where('unit_id', $unitId)
            ->where('status', 'allocated')
            ->whereNull('cancelled_at')
            ->sum('quantity');

        return bcsub(bcadd((string) $physicalQuantity, '0', 4), bcadd((string) $allocatedQuantity, '0', 4), 4);
    }

    private function lineAllocatedQuantity(ShipmentLine $shipmentLine): string
    {
        $quantity = ShipmentLotAllocation::query()
            ->where('shipment_line_id', $shipmentLine->id)
            ->where('status', 'allocated')
            ->whereNull('cancelled_at')
            ->sum('quantity');

        return bcadd((string) $quantity, '0', 4);
    }
}
