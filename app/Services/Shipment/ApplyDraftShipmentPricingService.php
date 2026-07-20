<?php

namespace App\Services\Shipment;

use App\Exceptions\Shipment\ShipmentDraftException;
use App\Models\ShipmentHeader;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use App\Services\Pricing\ResolvePriceService;
use Illuminate\Support\Facades\DB;

class ApplyDraftShipmentPricingService
{
    public function __construct(
        private readonly ResolvePriceService $resolvePriceService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function apply(ShipmentHeader $shipment, ?string $reason = null): ShipmentHeader
    {
        return DB::transaction(function () use ($shipment, $reason): ShipmentHeader {
            $shipment = ShipmentHeader::query()
                ->with(['customer', 'lines.product', 'lines.unit'])
                ->lockForUpdate()
                ->findOrFail($shipment->id);

            if ($shipment->status !== 'draft') {
                throw ShipmentDraftException::notDraft($shipment->id, $shipment->status);
            }

            $pricedLineIds = [];

            foreach ($shipment->lines as $line) {
                $resolved = $this->resolvePriceService->resolve(
                    customer: $shipment->customer,
                    product: $line->product,
                    pricingDate: $shipment->document_date,
                    unitId: $line->unit_id,
                );

                $line->update([
                    'draft_unit_price' => $resolved->unitPrice,
                    'draft_price_list_id' => $resolved->priceListId,
                    'draft_price_rule_id' => $resolved->priceRuleId,
                    'draft_price_source' => $resolved->source,
                    'draft_price_reason' => $resolved->reason,
                    'draft_priced_at' => now(),
                ]);

                $pricedLineIds[] = $line->id;
            }

            $this->auditLogService->record(new AuditLogData(
                event: 'shipment.draft_priced',
                auditable: $shipment,
                afterValues: [
                    'status' => $shipment->status,
                    'priced_line_ids' => $pricedLineIds,
                ],
                reason: $reason,
            ));

            return $shipment->refresh()->load(['customer', 'lines.product', 'lines.unit', 'lines.draftPriceList', 'lines.draftPriceRule']);
        });
    }
}

