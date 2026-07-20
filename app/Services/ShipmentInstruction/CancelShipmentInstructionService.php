<?php

namespace App\Services\ShipmentInstruction;

use App\Exceptions\Shipment\ShipmentInstructionException;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\ShipmentInstruction;
use App\Models\ShipmentInstructionLine;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

class CancelShipmentInstructionService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function cancel(ShipmentInstruction $instruction, string $reason): ShipmentInstruction
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ShipmentInstructionException::emptyCancellationReason();
        }

        return DB::transaction(function () use ($instruction, $reason): ShipmentInstruction {
            $instruction = ShipmentInstruction::query()
                ->with('lines')
                ->lockForUpdate()
                ->findOrFail($instruction->id);

            if ($instruction->status === 'cancelled' || $instruction->cancelled_at !== null) {
                throw ShipmentInstructionException::alreadyCancelled($instruction->id);
            }

            $hasPicked = $instruction->lines->contains(
                fn (ShipmentInstructionLine $line): bool => bccomp((string) $line->picked_quantity, '0.0000', 4) > 0,
            );

            if ($hasPicked) {
                throw ShipmentInstructionException::alreadyPicked($instruction->id);
            }

            foreach ($instruction->lines as $instructionLine) {
                $salesOrderLine = SalesOrderLine::query()
                    ->lockForUpdate()
                    ->findOrFail($instructionLine->sales_order_line_id);

                $salesOrderLine->update([
                    'remaining_quantity' => bcadd((string) $salesOrderLine->remaining_quantity, (string) $instructionLine->quantity, 4),
                ]);
            }

            $instruction->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_reason' => $reason,
            ]);

            $this->refreshSalesOrderStatuses($instruction);

            $this->auditLogService->record(new AuditLogData(
                event: 'shipment_instruction.cancelled',
                auditable: $instruction->refresh(),
                beforeValues: ['status' => 'instructed'],
                afterValues: [
                    'status' => 'cancelled',
                    'cancelled_at' => $instruction->cancelled_at?->toISOString(),
                    'cancelled_reason' => $instruction->cancelled_reason,
                ],
                reason: $reason,
            ));

            return $instruction->refresh()->load(['customer', 'stockLocation', 'lines.salesOrderLine', 'lines.product', 'lines.unit']);
        });
    }

    private function refreshSalesOrderStatuses(ShipmentInstruction $instruction): void
    {
        foreach ($instruction->lines->pluck('sales_order_id')->unique() as $salesOrderId) {
            $salesOrder = SalesOrder::query()
                ->with('lines')
                ->lockForUpdate()
                ->findOrFail($salesOrderId);

            $hasRemaining = $salesOrder->lines->contains(
                fn (SalesOrderLine $line): bool => bccomp((string) $line->remaining_quantity, '0.0000', 4) > 0,
            );
            $hasInstructed = $salesOrder->lines->contains(
                fn (SalesOrderLine $line): bool => bccomp((string) $line->remaining_quantity, (string) $line->quantity, 4) < 0,
            );

            $salesOrder->update([
                'status' => $hasRemaining
                    ? ($hasInstructed ? 'partially_instructed' : 'received')
                    : 'instructed',
            ]);
        }
    }
}
