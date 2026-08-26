<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\ProductionLot;

class ProductLotCandidateSummaryService
{
    public function __construct(
        private readonly LotStockBalanceService $lotBalances,
        private readonly EvaluateLotProductCompatibilityService $compatibility,
        private readonly OperationalStartStockLotEligibilityService $lotEligibility,
    ) {}

    /** @return array{normal:string,approval_required:string,total:string,lot_count:int} */
    public function forProduct(Product $product, ?int $locationId = null): array
    {
        $normal = '0.0000';
        $conditional = '0.0000';
        $count = 0;
        $eligibleLotIds = $this->lotEligibility->eligibleLotIds();
        $lots = ProductionLot::query()
            ->where(function ($query) use ($eligibleLotIds): void {
                $query->where(function ($query): void {
                    $query->where('is_active', true)->where('status', 'active');
                });
                if ($eligibleLotIds !== []) {
                    $query->orWhereIn('id', $eligibleLotIds);
                }
            })
            ->get()
            ->keyBy('id');

        foreach ($this->lotBalances->all() as $balance) {
            if (($locationId !== null && $balance->stockLocationId !== $locationId)
                || bccomp($balance->availableQuantity, '0', 4) <= 0) {
                continue;
            }

            $lot = $lots->get($balance->productionLotId);
            if (! $lot) {
                continue;
            }

            $result = $this->compatibility->evaluate($product, $lot, (int) $product->inventory_unit_id);
            if ($result['status'] === 'compatible') {
                $normal = bcadd($normal, $balance->availableQuantity, 4);
                $count++;
            } elseif ($result['status'] === 'approval_required') {
                $conditional = bcadd($conditional, $balance->availableQuantity, 4);
                $count++;
            }
        }

        return [
            'normal' => $normal,
            'approval_required' => $conditional,
            'total' => bcadd($normal, $conditional, 4),
            'lot_count' => $count,
        ];
    }
}
