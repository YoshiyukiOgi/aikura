<?php

namespace App\Services\ShipmentPicking;

use App\Exceptions\Shipment\ShipmentPickException;
use App\Models\ShipmentInstruction;
use App\Models\ShipmentInstructionLine;
use App\Models\ShipmentPick;
use App\Models\ShipmentHeader;
use App\Models\ShipmentLine;
use App\Models\ShipmentLotAllocation;
use App\Models\StockLocation;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use App\Services\NumberSequence\NumberSequenceService;
use Illuminate\Support\Facades\DB;

class PickShipmentInstructionService
{
    public function __construct(
        private readonly NumberSequenceService $numberSequenceService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function pick(PickShipmentInstructionData $data): ShipmentPick
    {
        if ($data->lines === []) {
            throw ShipmentPickException::emptyLines();
        }

        return DB::transaction(function () use ($data): ShipmentPick {
            $instruction = ShipmentInstruction::query()
                ->with('lines')
                ->lockForUpdate()
                ->findOrFail($data->shipmentInstructionId);

            if ($instruction->status === 'cancelled') {
                throw ShipmentPickException::cancelledInstruction($instruction->id);
            }

            $lines = $this->lockAndValidateLines($instruction, $data->lines);
            $stockLocationId = $this->resolveStockLocationId($data->stockLocationId, $instruction);

            $pickNumber = $this->numberSequenceService
                ->next('shipment_pick')
                ->formatted;

            $pick = ShipmentPick::create([
                'pick_number' => $pickNumber,
                'status' => 'picked',
                'shipment_instruction_id' => $instruction->id,
                'pick_date' => $data->pickDate,
                'stock_location_id' => $stockLocationId,
                'note' => $data->note,
            ]);

            foreach (array_values($data->lines) as $index => $lineData) {
                $instructionLine = $lines[$index];
                $quantity = bcadd($lineData->quantity, '0', 4);

                $this->createPickLinesFromShipment($pick, $instruction, $instructionLine, $quantity, $lineData->note);

                $instructionLine->update([
                    'picked_quantity' => bcadd((string) $instructionLine->picked_quantity, $quantity, 4),
                ]);
            }

            $this->refreshInstructionStatus($instruction);

            $this->auditLogService->record(new AuditLogData(
                event: 'shipment_pick.created',
                auditable: $pick,
                afterValues: [
                    'pick_number' => $pick->pick_number,
                    'status' => $pick->status,
                    'shipment_instruction_id' => $pick->shipment_instruction_id,
                    'line_count' => count($data->lines),
                ],
                reason: $data->reason,
            ));

            return $pick->load(['shipmentInstruction', 'stockLocation', 'lines.shipmentInstructionLine', 'lines.product', 'lines.unit', 'lines.lotAllocations.productionLot', 'lines.lotAllocations.approvalRequest']);
        });
    }

    private function createPickLinesFromShipment(ShipmentPick $pick, ShipmentInstruction $instruction, ShipmentInstructionLine $instructionLine, string $quantity, ?string $note): void
    {
        $shipment = ShipmentHeader::query()
            ->where('source_shipment_instruction_id', $instruction->id)
            ->where('status', 'draft')
            ->lockForUpdate()
            ->first();
        if (! $shipment) {
            if (! $instructionLine->product->is_inventory_managed) {
                $pick->lines()->create([
                    'line_no' => $pick->lines()->count() + 1,
                    'shipment_instruction_line_id' => $instructionLine->id,
                    'product_id' => $instructionLine->product_id,
                    'quantity' => $quantity,
                    'unit_id' => $instructionLine->unit_id,
                    'note' => $note,
                ]);
                return;
            }
            throw ShipmentPickException::lotAllocationRequired($instructionLine->id);
        }

        $shipmentLines = ShipmentLine::query()
            ->where('shipment_header_id', $shipment->id)
            ->where(function ($query) use ($instructionLine): void {
                $query->where('shipment_instruction_line_id', $instructionLine->id)
                    ->orWhere(function ($query) use ($instructionLine): void {
                        $query->whereNull('shipment_instruction_line_id')->where('line_no', $instructionLine->line_no)->where('product_id', $instructionLine->product_id);
                    });
            })
            ->orderBy('line_no')
            ->lockForUpdate()
            ->get();
        if ($shipmentLines->isEmpty()) {
            throw ShipmentPickException::lotAllocationRequired($instructionLine->id);
        }

        $allocations = ShipmentLotAllocation::query()
            ->with('approvalRequest')
            ->whereIn('shipment_line_id', $shipmentLines->pluck('id'))
            ->where('status', 'allocated')
            ->whereNull('cancelled_at')
            ->lockForUpdate()
            ->get();
        $total = $allocations->reduce(fn (string $sum, ShipmentLotAllocation $allocation): string => bcadd($sum, (string) $allocation->quantity, 4), '0.0000');
        if ($allocations->isEmpty() || bccomp($total, $quantity, 4) !== 0) {
            throw ShipmentPickException::lotAllocationIncomplete($instructionLine->id, $quantity, $total);
        }

        foreach ($shipmentLines as $shipmentLine) {
            $lineAllocations = $allocations->where('shipment_line_id', $shipmentLine->id);
            $lineTotal = $lineAllocations->reduce(fn (string $sum, ShipmentLotAllocation $allocation): string => bcadd($sum, (string) $allocation->quantity, 4), '0.0000');
            if ($lineAllocations->isEmpty() || bccomp($lineTotal, (string) $shipmentLine->quantity, 4) !== 0) {
                throw ShipmentPickException::lotAllocationIncomplete($instructionLine->id, (string) $shipmentLine->quantity, $lineTotal);
            }

            $pickLine = $pick->lines()->create([
                'line_no' => $pick->lines()->count() + 1,
                'shipment_instruction_line_id' => $instructionLine->id,
                'product_id' => $instructionLine->product_id,
                'quantity' => $shipmentLine->quantity,
                'unit_id' => $instructionLine->unit_id,
                'note' => $shipmentLine->note ?: $note,
            ]);

            foreach ($lineAllocations as $allocation) {
                if ($allocation->alcohol_compliance_status === 'analysis_required') {
                    throw ShipmentPickException::lotAlcoholAnalysisRequired($allocation->production_lot_id);
                }
                if ($allocation->alcohol_compliance_status === 'out_of_range'
                    && $allocation->approval_request_id !== null
                    && $allocation->approvalRequest?->status !== 'approved') {
                    throw ShipmentPickException::lotApprovalRequired($allocation->production_lot_id);
                }
                $allocation->update(['shipment_pick_line_id' => $pickLine->id]);
            }
            $shipmentLine->update(['source_shipment_pick_line_id' => $pickLine->id]);
        }

        $shipment->update(['source_shipment_pick_id' => $pick->id]);
    }

    /**
     * @param array<int, PickShipmentInstructionLineData> $lineDataList
     * @return array<int, ShipmentInstructionLine>
     */
    private function lockAndValidateLines(ShipmentInstruction $instruction, array $lineDataList): array
    {
        $lines = [];
        $seenInstructionLineIds = [];

        foreach ($lineDataList as $lineData) {
            $quantity = bcadd($lineData->quantity, '0', 4);

            if (bccomp($quantity, '0.0000', 4) <= 0) {
                throw ShipmentPickException::invalidQuantity($quantity);
            }

            $instructionLine = ShipmentInstructionLine::query()
                ->lockForUpdate()
                ->findOrFail($lineData->shipmentInstructionLineId);

            if ($instructionLine->shipment_instruction_id !== $instruction->id) {
                throw ShipmentPickException::lineDoesNotBelongToInstruction($instructionLine->id, $instruction->id);
            }

            if (isset($seenInstructionLineIds[$instructionLine->id])) {
                throw ShipmentPickException::duplicateInstructionLine($instructionLine->id);
            }

            $seenInstructionLineIds[$instructionLine->id] = true;
            $remainingQuantity = bcsub((string) $instructionLine->quantity, (string) $instructionLine->picked_quantity, 4);

            if (bccomp($quantity, $remainingQuantity, 4) > 0) {
                throw ShipmentPickException::exceedsRemainingQuantity($instructionLine->id, $remainingQuantity, $quantity);
            }

            $lines[] = $instructionLine;
        }

        return $lines;
    }

    private function resolveStockLocationId(?int $stockLocationId, ShipmentInstruction $instruction): ?int
    {
        if ($stockLocationId !== null) {
            return StockLocation::query()->findOrFail($stockLocationId)->id;
        }

        return $instruction->stock_location_id;
    }

    private function refreshInstructionStatus(ShipmentInstruction $instruction): void
    {
        $instruction->load('lines');

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
