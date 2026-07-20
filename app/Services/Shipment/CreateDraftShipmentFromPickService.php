<?php

namespace App\Services\Shipment;

use App\Exceptions\Shipment\ShipmentDraftException;
use App\Models\ShipmentHeader;
use App\Models\ShipmentPick;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use App\Services\NumberSequence\NumberSequenceService;
use Illuminate\Support\Facades\DB;

class CreateDraftShipmentFromPickService
{
    public function __construct(
        private readonly NumberSequenceService $numberSequenceService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function create(CreateDraftShipmentFromPickData $data): ShipmentHeader
    {
        return DB::transaction(function () use ($data): ShipmentHeader {
            $pick = ShipmentPick::query()
                ->with(['shipmentInstruction.customer', 'shipmentInstruction.lines.salesOrder', 'lines'])
                ->lockForUpdate()
                ->findOrFail($data->shipmentPickId);

            if ($pick->cancelled_at !== null || $pick->status === 'cancelled') {
                throw ShipmentDraftException::pickCancelled($pick->id);
            }

            if ($pick->lines->isEmpty()) {
                throw ShipmentDraftException::pickHasNoLines($pick->id);
            }

            $existing = ShipmentHeader::query()->where('source_shipment_pick_id', $pick->id)->first();
            if ($existing) {
                if ($existing->status === 'draft') {
                    return $existing->load(['customer', 'sourceShipmentPick', 'lines.sourceShipmentPickLine', 'lines.product', 'lines.unit', 'lines.lotAllocations.productionLot']);
                }
                throw ShipmentDraftException::pickAlreadyConverted($pick->id);
            }

            $instruction = $pick->shipmentInstruction;
            $customer = $instruction->customer;
            $salesOrder = $instruction->lines->first()?->salesOrder;
            $documentDate = $data->documentDate ?? $pick->pick_date->toDateString();
            $settlementReceivableCategoryIds = $instruction->lines
                ->pluck('salesOrder.settlement_receivable_category_id')
                ->filter()
                ->unique()
                ->values();
            $settlementReceivableCategoryId = $settlementReceivableCategoryIds->count() > 0
                ? $settlementReceivableCategoryIds->sole()
                : $customer->settlement_receivable_category_id;

            $documentNumber = $this->numberSequenceService
                ->next('shipment_document')
                ->formatted;

            $shipment = ShipmentHeader::create([
                'document_number' => $documentNumber,
                'status' => 'draft',
                'customer_id' => $customer->id,
                'transaction_category_id' => $customer->transaction_category_id,
                'settlement_receivable_category_id' => $settlementReceivableCategoryId,
                'billing_cycle_id' => $customer->billing_cycle_id,
                'document_date' => $documentDate,
                'order_date' => $instruction->instruction_date?->toDateString(),
                'scheduled_shipment_date' => $instruction->scheduled_shipment_date?->toDateString(),
                'billing_target_date' => $data->billingTargetDate ?? $documentDate,
                'liquor_tax_transfer_date' => $data->liquorTaxTransferDate,
                'source_shipment_pick_id' => $pick->id,
                'note' => $data->note ?? $salesOrder?->note ?? $pick->note,
            ]);

            foreach ($pick->lines->sortBy('line_no')->values() as $index => $pickLine) {
                $shipment->lines()->create([
                    'line_no' => $index + 1,
                    'product_id' => $pickLine->product_id,
                    'quantity' => $pickLine->quantity,
                    'unit_id' => $pickLine->unit_id,
                    'source_shipment_pick_line_id' => $pickLine->id,
                    'shipment_instruction_line_id' => $pickLine->shipment_instruction_line_id,
                    'note' => $pickLine->note,
                ]);
            }

            $this->auditLogService->record(new AuditLogData(
                event: 'shipment.draft_created_from_pick',
                auditable: $shipment,
                afterValues: [
                    'document_number' => $shipment->document_number,
                    'status' => $shipment->status,
                    'shipment_pick_id' => $pick->id,
                    'line_count' => $pick->lines->count(),
                ],
                reason: $data->reason,
            ));

            return $shipment->load(['customer', 'sourceShipmentPick', 'lines.sourceShipmentPickLine', 'lines.product', 'lines.unit']);
        });
    }
}
