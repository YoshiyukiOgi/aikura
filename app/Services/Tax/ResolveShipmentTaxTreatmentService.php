<?php

namespace App\Services\Tax;

use App\Models\ConsumptionTaxCategory;
use App\Models\LiquorTaxCategory;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\ShipmentHeader;
use DomainException;

class ResolveShipmentTaxTreatmentService
{
    public function __construct(
        private readonly ResolveProductConsumptionTaxCategoryService $productConsumptionTaxCategoryService,
        private readonly CalculateLiquorTaxService $calculateLiquorTaxService,
    ) {
    }

    public function resolve(ShipmentHeader $shipment): ResolvedShipmentTaxTreatment
    {
        $category = $shipment->settlementReceivableCategory
            ?? $shipment->customer?->settlementReceivableCategory;

        if ($category === null) {
            throw new DomainException('出荷の精算・売掛区分が設定されていません。');
        }

        $liquorTreatment = match ($category->liquor_tax_type) {
            'taxable' => 'taxable',
            'untaxed', 'untaxed_transfer' => 'untaxed_transfer',
            'exempt' => $category->export_type === 'export' ? 'export_exempt' : 'exempt',
            'not_applicable', 'out_of_scope' => 'not_applicable',
            default => throw new DomainException("未対応の酒税取引区分です: {$category->liquor_tax_type}"),
        };

        return new ResolvedShipmentTaxTreatment(
            settlementCategory: $category,
            liquorTaxTreatment: $liquorTreatment,
            consumptionTaxTreatment: (string) $category->consumption_tax_type,
            exportType: (string) $category->export_type,
            requiresReview: (bool) $category->requires_tax_review,
            requiresEvidence: (bool) $category->requires_evidence,
        );
    }

    public function consumptionTaxCategory(Product $product, ResolvedShipmentTaxTreatment $treatment): ConsumptionTaxCategory
    {
        $code = match ($treatment->consumptionTaxTreatment) {
            'taxable' => null,
            'export_exempt' => 'export_exempt',
            'non_taxable' => 'non_taxable',
            'out_of_scope', 'not_applicable' => 'out_of_scope',
            'exempt' => 'tax_exempt',
            default => throw new DomainException("未対応の消費税取引区分です: {$treatment->consumptionTaxTreatment}"),
        };

        return $code === null
            ? $this->productConsumptionTaxCategoryService->resolve($product)
            : ConsumptionTaxCategory::query()->where('code', $code)->firstOrFail();
    }

    public function liquorTax(
        Product $product,
        string $quantity,
        string $date,
        ResolvedShipmentTaxTreatment $treatment,
        ?ProductionLot $lot = null,
    ): CalculatedLiquorTax {
        if ($treatment->liquorTaxTreatment === 'taxable') {
            return $lot === null
                ? $this->calculateLiquorTaxService->calculate($product, $quantity, $date)
                : $this->calculateLiquorTaxService->calculateForLot($product, $lot, $quantity, $date);
        }

        $categoryCode = match ($treatment->liquorTaxTreatment) {
            'export_exempt', 'exempt' => 'export_exempt_liquor',
            'untaxed_transfer' => 'untaxed_transfer_liquor',
            default => 'non_liquor',
        };

        $category = LiquorTaxCategory::query()->where('code', $categoryCode)->firstOrFail();

        return new CalculatedLiquorTax(
            $category,
            null,
            $this->calculateLiquorTaxService->volumeKl($product, $quantity, $lot),
            '0.00',
            null,
        );
    }
}
