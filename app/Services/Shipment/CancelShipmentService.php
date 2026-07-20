<?php

namespace App\Services\Shipment;

use App\Exceptions\Shipment\ShipmentCancellationException;
use App\Models\ShipmentHeader;
use App\Models\InvoiceLine;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use App\Services\Inventory\ReverseShipmentStockMovementsService;
use App\Services\StateMachine\StatusTransitionService;
use App\Services\Tax\EnsureLiquorTaxFilingPeriodIsOpenService;
use Illuminate\Support\Facades\DB;

class CancelShipmentService
{
    public function __construct(
        private readonly StatusTransitionService $statusTransitionService,
        private readonly AuditLogService $auditLogService,
        private readonly ReverseShipmentStockMovementsService $reverseShipmentStockMovementsService,
        private readonly EnsureLiquorTaxFilingPeriodIsOpenService $ensureLiquorTaxFilingPeriodIsOpenService,
    ) {
    }

    public function cancel(ShipmentHeader $shipment, string $reason): ShipmentHeader
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ShipmentCancellationException::emptyReason();
        }

        return DB::transaction(function () use ($shipment, $reason): ShipmentHeader {
            $shipment = ShipmentHeader::query()
                ->with('lines')
                ->lockForUpdate()
                ->findOrFail($shipment->id);

            $from = $shipment->status;

            if ($from === 'confirmed' && InvoiceLine::query()
                ->where('shipment_header_id', $shipment->id)
                ->whereHas('invoiceHeader', fn ($query) => $query->where('status', 'confirmed'))
                ->exists()) {
                throw ShipmentCancellationException::alreadyInvoiced($shipment->id);
            }

            if ($shipment->status !== 'draft') {
                $liquorTaxDate = ($shipment->liquor_tax_transfer_date ?? $shipment->document_date)->toDateString();
                $this->ensureLiquorTaxFilingPeriodIsOpenService->ensureOpen($liquorTaxDate);
            }

            $this->statusTransitionService->transition(
                model: $shipment,
                machine: 'shipment',
                to: 'cancelled',
                reason: $reason,
                audit: false,
            );

            $shipment->refresh();
            $shipment->update([
                'cancelled_at' => now(),
                'cancelled_reason' => $reason,
            ]);

            if ($from === 'draft') {
                $shipment->update(['source_shipment_pick_id' => null]);
            }

            $this->reverseShipmentStockMovementsService->reverseForCancelledShipment($shipment, $reason);

            $this->auditLogService->record(new AuditLogData(
                event: 'shipment.cancelled',
                auditable: $shipment->refresh(),
                beforeValues: ['status' => $from],
                afterValues: [
                    'status' => 'cancelled',
                    'cancelled_at' => $shipment->cancelled_at?->toISOString(),
                    'cancelled_reason' => $shipment->cancelled_reason,
                ],
                reason: $reason,
            ));

            return $shipment->refresh()->load('lines');
        });
    }
}
