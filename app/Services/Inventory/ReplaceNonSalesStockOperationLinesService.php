<?php

namespace App\Services\Inventory;

use App\Exceptions\Inventory\NonSalesStockOperationException;
use App\Models\NonSalesStockOperationHeader;
use App\Models\ProductionLot;
use App\Models\Product;
use App\Models\SalesReturnLine;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Services\Tax\CalculateLiquorTaxService;
use DomainException;

class ReplaceNonSalesStockOperationLinesService
{
    private const OUTGOING_TYPES = ['breakage', 'disposal', 'loss', 'self_consumption', 'gift', 'sample', 'inspection'];

    public function __construct(private readonly CalculateLiquorTaxService $calculateLiquorTaxService)
    {
    }

    /**
     * @param array<int, CreateNonSalesStockOperationLineData> $lines
     */
    public function replace(NonSalesStockOperationHeader $header, string $operationType, string $operationDate, string $reason, array $lines): void
    {
        if ($lines === []) {
            throw NonSalesStockOperationException::emptyLines();
        }

        $resolvedLines = [];
        foreach ($lines as $lineData) {
            if (bccomp($lineData->quantity, '0', 4) === 0) {
                throw NonSalesStockOperationException::zeroQuantity();
            }
            if (in_array($operationType, self::OUTGOING_TYPES, true) && bccomp($lineData->quantity, '0', 4) >= 0) {
                throw new DomainException('出庫区分の数量は負数で入力してください。');
            }

            $productionLot = ProductionLot::query()->lockForUpdate()->findOrFail($lineData->productionLotId);
            $stockLocation = StockLocation::query()->lockForUpdate()->findOrFail($lineData->stockLocationId);
            if (! $productionLot->is_active || $productionLot->status !== 'active') {
                throw new DomainException('使用できないロットです。');
            }
            if ($productionLot->unit_id === null) {
                throw new DomainException('ロットの在庫単位が設定されていません。');
            }
            if (! $stockLocation->is_active || ! $stockLocation->is_inventory_managed) {
                throw NonSalesStockOperationException::inactiveStockLocation($stockLocation->id);
            }
            $product = $lineData->productId === null ? null : Product::query()->findOrFail($lineData->productId);
            if ($product === null && $lineData->sourceSalesReturnLineId !== null) {
                $returnLine = SalesReturnLine::query()->findOrFail($lineData->sourceSalesReturnLineId);
                $product = Product::query()->find($returnLine->product_id);
            }
            $resolvedLines[] = [$lineData, $productionLot, $stockLocation, $product];
        }

        $header->lines()->delete();

        $taxTreatment = match ($operationType) {
            'self_consumption', 'gift', 'sample', 'inspection' => 'taxable',
            'return_to_manufacturing' => 'return',
            'breakage', 'disposal', 'loss' => 'review',
            default => 'not_applicable',
        };
        $header->update([
            'liquor_tax_treatment' => $taxTreatment,
            'requires_tax_review' => $taxTreatment === 'review',
        ]);

        foreach ($resolvedLines as $index => [$lineData, $productionLot, $stockLocation, $product]) {
            $liquorTax = null;
            if ($product !== null && in_array($taxTreatment, ['taxable', 'review'], true)) {
                $liquorTax = $this->calculateLiquorTaxService->calculateForLot(
                    $product,
                    $productionLot,
                    ltrim(bcadd($lineData->quantity, '0', 4), '-'),
                    $operationDate,
                );
            }
            $line = $header->lines()->create([
                'source_sales_return_line_id' => $lineData->sourceSalesReturnLineId,
                'line_no' => $index + 1,
                'product_id' => $product?->id,
                'stock_location_id' => $stockLocation->id,
                'unit_id' => $productionLot->unit_id,
                'quantity' => bcadd($lineData->quantity, '0', 4),
                'production_lot_id' => $productionLot->id,
                'lot_code' => $productionLot->lot_code,
                'reason' => $lineData->reason,
                'note' => $lineData->note,
                'liquor_tax_category_id' => $liquorTax?->category->id,
                'liquor_tax_category_code' => $liquorTax?->category->code,
                'liquor_tax_category_name' => $liquorTax?->category->name,
                'liquor_taxability' => $liquorTax?->category->taxability,
                'liquor_tax_rule_id' => $liquorTax?->rule?->id,
                'liquor_tax_calculation_method' => $liquorTax?->rule?->calculation_method,
                'liquor_taxable_kl' => $liquorTax?->taxableKl,
                'liquor_tax_per_kl' => $liquorTax?->taxPerKl,
                'liquor_tax_reduction_rate' => $liquorTax?->rule?->reduction_rate,
                'liquor_tax_estimated_amount' => $liquorTax?->estimatedAmount,
            ]);

            $movement = StockMovement::create([
                'status' => 'confirmed',
                'movement_type' => 'non_sales_'.$operationType,
                'movement_date' => $operationDate,
                'stock_location_id' => $stockLocation->id,
                'unit_id' => $productionLot->unit_id,
                'quantity' => bcadd($lineData->quantity, '0', 4),
                'source_type' => 'non_sales_stock_operation',
                'source_document_number' => $header->operation_number,
                'source_line_no' => $line->line_no,
                'production_lot_id' => $productionLot->id,
                'lot_code' => $productionLot->lot_code,
                'confirmed_at' => now(),
                'reason' => $lineData->reason ?? $reason,
                'note' => $lineData->note,
            ]);
            $line->update(['stock_movement_id' => $movement->id]);
        }
    }
}
