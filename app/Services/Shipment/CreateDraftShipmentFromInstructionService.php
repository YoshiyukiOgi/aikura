<?php

namespace App\Services\Shipment;

use App\Exceptions\Shipment\ShipmentDraftException;
use App\Models\ShipmentHeader;
use App\Models\ShipmentInstruction;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use App\Services\NumberSequence\NumberSequenceService;
use Illuminate\Support\Facades\DB;

class CreateDraftShipmentFromInstructionService
{
    public function __construct(private readonly NumberSequenceService $numberSequenceService, private readonly AuditLogService $auditLogService)
    {
    }

    public function create(ShipmentInstruction $instruction): ShipmentHeader
    {
        return DB::transaction(function () use ($instruction): ShipmentHeader {
            $instruction = ShipmentInstruction::query()->with(['customer', 'lines.salesOrder'])->lockForUpdate()->findOrFail($instruction->id);
            if ($instruction->status === 'cancelled' || $instruction->lines->isEmpty()) {
                throw ShipmentDraftException::emptyLines();
            }

            $existing = ShipmentHeader::query()->where('source_shipment_instruction_id', $instruction->id)->where('status', '!=', 'cancelled')->first();
            if ($existing) {
                return $existing->load(['customer', 'lines.product', 'lines.unit', 'sourceShipmentInstruction.lines.salesOrder']);
            }

            $salesOrder = $instruction->lines->first()?->salesOrder;
            $shipment = ShipmentHeader::create([
                'document_number' => $this->numberSequenceService->next('shipment_document')->formatted,
                'status' => 'draft',
                'customer_id' => $instruction->customer_id,
                'transaction_category_id' => $instruction->customer->transaction_category_id,
                'settlement_receivable_category_id' => $salesOrder?->settlement_receivable_category_id ?? $instruction->customer->settlement_receivable_category_id,
                'billing_cycle_id' => $instruction->customer->billing_cycle_id,
                'document_date' => now()->toDateString(),
                'order_date' => $salesOrder?->order_date?->toDateString(),
                'scheduled_shipment_date' => $instruction->scheduled_shipment_date?->toDateString(),
                'billing_target_date' => now()->toDateString(),
                'source_shipment_instruction_id' => $instruction->id,
                'note' => $salesOrder?->note ?? $instruction->note,
            ]);

            foreach ($instruction->lines->sortBy('line_no')->values() as $index => $line) {
                $shipment->lines()->create(['line_no' => $index + 1, 'product_id' => $line->product_id, 'quantity' => $line->quantity, 'unit_id' => $line->unit_id, 'shipment_instruction_line_id' => $line->id, 'note' => $line->note]);
            }

            $this->auditLogService->record(new AuditLogData(event: 'shipment.draft_created_from_instruction', auditable: $shipment, afterValues: ['document_number' => $shipment->document_number, 'shipment_instruction_id' => $instruction->id, 'status' => 'draft']));

            return $shipment->load(['customer', 'lines.product', 'lines.unit', 'sourceShipmentInstruction.lines.salesOrder']);
        });
    }
}
