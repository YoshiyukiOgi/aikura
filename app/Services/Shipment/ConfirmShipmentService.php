<?php

namespace App\Services\Shipment;

use App\Exceptions\Shipment\ShipmentConfirmationException;
use App\Models\ShipmentHeader;
use App\Models\ShipmentLotAllocation;
use App\Services\Tax\CalculatedLiquorTax;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use App\Services\Inventory\CreateShipmentStockMovementsService;
use App\Services\StateMachine\StatusTransitionService;
use App\Services\Tax\EnsureLiquorTaxFilingPeriodIsOpenService;
use App\Services\Tax\ResolveConsumptionTaxRateService;
use App\Services\Tax\ResolveShipmentTaxTreatmentService;
use Illuminate\Support\Facades\DB;

class ConfirmShipmentService
{
    public function __construct(
        private readonly StatusTransitionService $statusTransitionService,
        private readonly AuditLogService $auditLogService,
        private readonly ResolveConsumptionTaxRateService $resolveConsumptionTaxRateService,
        private readonly ResolveShipmentTaxTreatmentService $resolveShipmentTaxTreatmentService,
        private readonly CreateShipmentStockMovementsService $createShipmentStockMovementsService,
        private readonly EnsureLiquorTaxFilingPeriodIsOpenService $ensureLiquorTaxFilingPeriodIsOpenService,
    ) {
    }

    public function confirm(ShipmentHeader $shipment, ?string $reason = null): ShipmentHeader
    {
        return DB::transaction(function () use ($shipment, $reason): ShipmentHeader {
            $shipment = ShipmentHeader::query()
                ->with(['customer.settlementReceivableCategory', 'settlementReceivableCategory', 'lines.product', 'lines.unit', 'lines.draftPriceList', 'lines.draftPriceRule', 'lines.lotAllocations.productionLot.capacityUnit', 'lines.lotAllocations.approvalRequest'])
                ->lockForUpdate()
                ->findOrFail($shipment->id);

            if ($shipment->status !== 'draft') {
                throw ShipmentConfirmationException::notDraft($shipment->id, $shipment->status);
            }

            if ($shipment->lines->isEmpty()) {
                throw ShipmentConfirmationException::noLines($shipment->id);
            }

            $liquorTaxDate = ($shipment->liquor_tax_transfer_date ?? $shipment->document_date)->toDateString();
            $this->ensureLiquorTaxFilingPeriodIsOpenService->ensureOpen($liquorTaxDate);
            $taxTreatment = $this->resolveShipmentTaxTreatmentService->resolve($shipment);
            $settlementCategory = $taxTreatment->settlementCategory;

            $shipment->forceFill([
                'confirmed_settlement_receivable_category_id' => $settlementCategory->id,
                'confirmed_settlement_receivable_category_code' => $settlementCategory->code,
                'confirmed_settlement_receivable_category_name' => $settlementCategory->name,
                'confirmed_liquor_tax_treatment' => $taxTreatment->liquorTaxTreatment,
                'confirmed_consumption_tax_treatment' => $taxTreatment->consumptionTaxTreatment,
                'confirmed_export_type' => $taxTreatment->exportType,
                'confirmed_receivable_method' => $settlementCategory->receivable_method,
                'confirmed_invoice_required' => $settlementCategory->invoice_required,
                'confirmed_requires_tax_review' => $taxTreatment->requiresReview,
                'confirmed_requires_evidence' => $taxTreatment->requiresEvidence,
            ])->save();

            foreach ($shipment->lines as $line) {
                if ($line->draft_unit_price === null || $line->draft_price_list_id === null || $line->draft_price_rule_id === null) {
                    throw ShipmentConfirmationException::lineHasNoDraftPrice($line->id);
                }

                $product = $line->product;
                $unit = $line->unit;
                $taxCategory = $this->resolveShipmentTaxTreatmentService->consumptionTaxCategory($product, $taxTreatment);
                $taxRate = null;

                if ($taxCategory->requires_tax_rate) {
                    $taxRate = $this->resolveConsumptionTaxRateService
                        ->resolve($taxCategory, $shipment->document_date->toDateString())
                        ->rate;
                }
                $allocations = $line->lotAllocations->where('status', 'allocated')->whereNull('cancelled_at')->values();
                $allocatedQuantity = $allocations->reduce(fn (string $sum, ShipmentLotAllocation $allocation): string => bcadd($sum, (string) $allocation->quantity, 4), '0.0000');
                if ($shipment->source_shipment_pick_id !== null && $product->is_inventory_managed
                    && ($allocations->isEmpty() || bccomp($allocatedQuantity, (string) $line->quantity, 4) !== 0)) {
                    throw ShipmentConfirmationException::lineLotAllocationIncomplete($line->id, (string) $line->quantity, $allocatedQuantity);
                }

                $liquorTax = $this->resolveShipmentTaxTreatmentService->liquorTax($product, $line->quantity, $liquorTaxDate, $taxTreatment);
                if ($allocations->isNotEmpty()) {
                    $taxes = collect();
                    foreach ($allocations as $allocation) {
                        if ($allocation->alcohol_compliance_status === 'out_of_range'
                            && $allocation->approval_request_id !== null
                            && $allocation->approvalRequest?->status !== 'approved') {
                            throw ShipmentConfirmationException::lineLotAllocationIncomplete($line->id, (string) $line->quantity, 'alcohol approval pending');
                        }
                        $tax = $this->resolveShipmentTaxTreatmentService->liquorTax($product, (string) $allocation->quantity, $liquorTaxDate, $taxTreatment, $allocation->productionLot);
                        $allocation->update([
                            'actual_alcohol_percentage' => $allocation->productionLot->alcohol_percentage,
                            'liquor_tax_category_id' => $tax->category->id,
                            'liquor_tax_rule_id' => $tax->rule?->id,
                            'liquor_taxable_kl' => $tax->taxableKl,
                            'liquor_tax_per_kl' => $tax->taxPerKl,
                            'liquor_tax_estimated_amount' => $tax->estimatedAmount,
                        ]);
                        $taxes->push($tax);
                    }
                    $firstTax = $taxes->first();
                    $liquorTax = new CalculatedLiquorTax(
                        category: $firstTax->category,
                        rule: $taxes->pluck('rule.id')->filter()->unique()->count() === 1 ? $firstTax->rule : null,
                        taxableKl: $taxes->reduce(fn (string $sum, CalculatedLiquorTax $tax): string => bcadd($sum, $tax->taxableKl, 6), '0.000000'),
                        estimatedAmount: $taxes->reduce(fn (string $sum, CalculatedLiquorTax $tax): string => bcadd($sum, $tax->estimatedAmount, 2), '0.00'),
                        taxPerKl: $taxes->pluck('taxPerKl')->filter()->unique()->count() === 1 ? $firstTax->taxPerKl : null,
                    );
                }

                $line->update([
                    'confirmed_product_code' => $product->product_code,
                    'confirmed_product_name' => $product->name,
                    'confirmed_display_name' => $product->display_name,
                    'confirmed_product_type' => $product->product_type,
                    'confirmed_unit_code' => $unit->code,
                    'confirmed_unit_name' => $unit->name,
                    'confirmed_quantity' => $line->quantity,
                    'confirmed_unit_price' => $line->draft_unit_price,
                    'confirmed_price_list_id' => $line->draft_price_list_id,
                    'confirmed_price_rule_id' => $line->draft_price_rule_id,
                    'confirmed_price_source' => $line->draft_price_source,
                    'confirmed_price_reason' => $line->draft_price_reason,
                    'confirmed_capacity_value' => $product->capacity_value,
                    'confirmed_capacity_unit_id' => $product->capacity_unit_id,
                    'confirmed_alcohol_percentage' => $product->alcohol_percentage,
                    'confirmed_rounding_method' => 'round',
                    'confirmed_consumption_tax_category_id' => $taxCategory->id,
                    'confirmed_consumption_tax_category_code' => $taxCategory->code,
                    'confirmed_consumption_tax_category_name' => $taxCategory->name,
                    'confirmed_consumption_taxability' => $taxCategory->taxability,
                    'confirmed_consumption_tax_rate_id' => $taxRate?->id,
                    'confirmed_consumption_tax_rate' => $taxRate?->rate,
                    'confirmed_consumption_tax_rate_effective_from' => $taxRate?->effective_from,
                    'confirmed_liquor_tax_category_id' => $liquorTax->category->id,
                    'confirmed_liquor_tax_category_code' => $liquorTax->category->code,
                    'confirmed_liquor_tax_category_name' => $liquorTax->category->name,
                    'confirmed_liquor_taxability' => $liquorTax->category->taxability,
                    'confirmed_liquor_tax_rule_id' => $liquorTax->rule?->id,
                    'confirmed_liquor_tax_calculation_method' => $liquorTax->rule?->calculation_method,
                    'confirmed_liquor_taxable_kl' => $liquorTax->taxableKl,
                    'confirmed_liquor_tax_per_kl' => $liquorTax->taxPerKl,
                    'confirmed_liquor_tax_reduction_rate' => $liquorTax->rule?->reduction_rate ?? $liquorTax->category->reduction_rate,
                    'confirmed_liquor_tax_estimated_amount' => $liquorTax->estimatedAmount,
                    'confirmed_at' => now(),
                ]);
            }

            $this->statusTransitionService->transition(
                model: $shipment,
                machine: 'shipment',
                to: 'confirmed',
                reason: $reason,
                audit: false,
            );

            $this->createShipmentStockMovementsService->createForConfirmedShipment($shipment);

            $this->auditLogService->record(new AuditLogData(
                event: 'shipment.confirmed',
                auditable: $shipment->refresh(),
                beforeValues: ['status' => 'draft'],
                afterValues: [
                    'status' => 'confirmed',
                    'confirmed_line_ids' => $shipment->lines->pluck('id')->all(),
                ],
                reason: $reason,
            ));

            return $shipment->refresh()->load(['customer', 'lines.product', 'lines.unit']);
        });
    }
}
