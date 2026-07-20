<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\ProductionLot;

class EvaluateLotProductCompatibilityService
{
    public function __construct(
        private readonly EvaluateLotAlcoholCompatibilityService $alcoholCompatibility,
    ) {}

    /** @return array{status:string,selectable:bool,approval_required:bool,alcohol:array<string, mixed>} */
    public function evaluate(Product $product, ProductionLot $lot, int $requiredUnitId): array
    {
        $alcohol = $this->alcoholCompatibility->evaluate($product, $lot);

        if ($lot->unit_id === null || $lot->unit_id !== $requiredUnitId) {
            return $this->result('unit_mismatch', false, false, $alcohol);
        }

        if ($product->capacity_value !== null) {
            $capacityMatches = $lot->capacity_value !== null
                && $product->capacity_unit_id === $lot->capacity_unit_id
                && bccomp((string) $product->capacity_value, (string) $lot->capacity_value, 4) === 0;
            if (! $capacityMatches) {
                return $this->result('capacity_mismatch', false, false, $alcohol);
            }
        }

        if ($alcohol['status'] === 'analysis_required') {
            return $this->result('analysis_required', false, false, $alcohol);
        }

        if ($alcohol['status'] === 'out_of_range') {
            return $this->result('approval_required', true, true, $alcohol);
        }

        return $this->result('compatible', true, false, $alcohol);
    }

    /** @param array<string, mixed> $alcohol */
    private function result(string $status, bool $selectable, bool $approvalRequired, array $alcohol): array
    {
        return [
            'status' => $status,
            'selectable' => $selectable,
            'approval_required' => $approvalRequired,
            'alcohol' => $alcohol,
        ];
    }
}
