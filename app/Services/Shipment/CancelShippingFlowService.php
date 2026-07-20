<?php

namespace App\Services\Shipment;

use App\Exceptions\Shipment\ShipmentFlowCancellationException;
use App\Models\ShipmentHeader;
use App\Models\ShipmentInstruction;
use App\Models\SalesOrder;
use App\Services\ShipmentInstruction\CancelShipmentInstructionService;
use App\Services\ShipmentPicking\CancelShipmentPickService;
use Illuminate\Support\Facades\DB;

class CancelShippingFlowService
{
    public function __construct(
        private readonly CancelShipmentService $cancelShipmentService,
        private readonly CancelShipmentPickService $cancelShipmentPickService,
        private readonly CancelShipmentInstructionService $cancelShipmentInstructionService,
    ) {
    }

    public function cancel(ShipmentInstruction $instruction, string $reason): void
    {
        DB::transaction(function () use ($instruction, $reason): void {
            $instruction = ShipmentInstruction::query()->with(['lines.salesOrder', 'picks'])->lockForUpdate()->findOrFail($instruction->id);
            $pickIds = $instruction->picks->pluck('id');
            $shipments = ShipmentHeader::query()->where(function ($query) use ($instruction, $pickIds): void {
                $query->where('source_shipment_instruction_id', $instruction->id);
                if ($pickIds->isNotEmpty()) {
                    $query->orWhereIn('source_shipment_pick_id', $pickIds);
                }
            })->where('status', '!=', 'cancelled')->lockForUpdate()->get();

            if ($shipments->contains(fn (ShipmentHeader $shipment): bool => $shipment->status === 'confirmed')) {
                throw ShipmentFlowCancellationException::confirmedShipmentExists($instruction->id);
            }

            foreach ($shipments as $shipment) {
                $this->cancelShipmentService->cancel($shipment, $reason);
            }

            foreach ($instruction->picks->where('status', '!=', 'cancelled') as $pick) {
                $this->cancelShipmentPickService->cancel($pick, $reason);
            }

            if ($instruction->status !== 'cancelled') {
                $this->cancelShipmentInstructionService->cancel($instruction, $reason);
            }

            foreach ($instruction->lines->pluck('sales_order_id')->unique() as $salesOrderId) {
                $salesOrder = SalesOrder::query()->findOrFail($salesOrderId);

                $salesOrder->update([
                    'status' => 'received',
                    'awaiting_shipment_instruction' => false,
                    'shipment_returned_at' => now(),
                    'shipment_returned_reason' => $reason,
                ]);
            }
        });
    }
}
