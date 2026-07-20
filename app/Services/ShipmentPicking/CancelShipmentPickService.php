<?php

namespace App\Services\ShipmentPicking;

use App\Exceptions\Shipment\ShipmentPickException;
use App\Models\ShipmentInstruction;
use App\Models\ShipmentInstructionLine;
use App\Models\ShipmentPick;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

class CancelShipmentPickService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function cancel(ShipmentPick $pick, string $reason): ShipmentPick
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ShipmentPickException::emptyCancellationReason();
        }

        return DB::transaction(function () use ($pick, $reason): ShipmentPick {
            $pick = ShipmentPick::query()
                ->with(['lines.shipmentInstructionLine', 'shipmentHeader'])
                ->lockForUpdate()
                ->findOrFail($pick->id);

            if ($pick->status === 'cancelled' || $pick->cancelled_at !== null) {
                throw ShipmentPickException::alreadyCancelled($pick->id);
            }

            if ($pick->shipmentHeader !== null) {
                throw ShipmentPickException::alreadyConvertedToShipment($pick->id);
            }

            foreach ($pick->lines as $pickLine) {
                $instructionLine = ShipmentInstructionLine::query()
                    ->lockForUpdate()
                    ->findOrFail($pickLine->shipment_instruction_line_id);

                $instructionLine->update([
                    'picked_quantity' => bcsub((string) $instructionLine->picked_quantity, (string) $pickLine->quantity, 4),
                ]);
            }

            $pick->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_reason' => $reason,
            ]);

            $instruction = ShipmentInstruction::query()
                ->with('lines')
                ->lockForUpdate()
                ->findOrFail($pick->shipment_instruction_id);
            $this->refreshInstructionStatus($instruction);

            $this->auditLogService->record(new AuditLogData(
                event: 'shipment_pick.cancelled',
                auditable: $pick->refresh(),
                beforeValues: ['status' => 'picked'],
                afterValues: [
                    'status' => 'cancelled',
                    'cancelled_at' => $pick->cancelled_at?->toISOString(),
                    'cancelled_reason' => $pick->cancelled_reason,
                ],
                reason: $reason,
            ));

            return $pick->refresh()->load(['shipmentInstruction', 'stockLocation', 'lines.shipmentInstructionLine', 'lines.product', 'lines.unit']);
        });
    }

    private function refreshInstructionStatus(ShipmentInstruction $instruction): void
    {
        $hasRemaining = $instruction->lines->contains(
            fn (ShipmentInstructionLine $line): bool => bccomp(
                bcsub((string) $line->quantity, (string) $line->picked_quantity, 4),
                '0.0000',
                4,
            ) > 0,
        );
        $hasPicked = $instruction->lines->contains(
            fn (ShipmentInstructionLine $line): bool => bccomp((string) $line->picked_quantity, '0.0000', 4) > 0,
        );

        $instruction->update([
            'status' => $hasRemaining
                ? ($hasPicked ? 'partially_picked' : 'instructed')
                : 'picked',
        ]);
    }
}
