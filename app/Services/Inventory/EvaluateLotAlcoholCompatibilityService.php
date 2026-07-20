<?php

namespace App\Services\Inventory;

use App\Models\AppSetting;
use App\Models\Product;
use App\Models\ProductionLot;

class EvaluateLotAlcoholCompatibilityService
{
    /** @return array{status:string,standard:?string,actual:?string,min:?string,max:?string,approval_required:bool} */
    public function evaluate(Product $product, ProductionLot $lot): array
    {
        if (! $product->is_alcohol) {
            return ['status' => 'not_applicable', 'standard' => null, 'actual' => null, 'min' => null, 'max' => null, 'approval_required' => false];
        }

        $settings = AppSetting::values([
            'alcohol_tolerance_lower' => '0.90',
            'alcohol_tolerance_upper' => '0.90',
            'alcohol_out_of_range_approval_required' => '1',
        ]);
        $standard = $product->alcohol_percentage === null ? null : bcadd((string) $product->alcohol_percentage, '0', 2);
        $actual = $lot->alcohol_percentage === null ? null : bcadd((string) $lot->alcohol_percentage, '0', 2);

        if ($standard === null || $actual === null || $lot->analysis_status !== 'confirmed') {
            return ['status' => 'analysis_required', 'standard' => $standard, 'actual' => $actual, 'min' => null, 'max' => null, 'approval_required' => false];
        }

        $min = bcsub($standard, (string) $settings['alcohol_tolerance_lower'], 2);
        $max = bcadd($standard, (string) $settings['alcohol_tolerance_upper'], 2);
        $status = bccomp($actual, $min, 2) >= 0 && bccomp($actual, $max, 2) <= 0 ? 'within_range' : 'out_of_range';

        return [
            'status' => $status,
            'standard' => $standard,
            'actual' => $actual,
            'min' => $min,
            'max' => $max,
            'approval_required' => $status === 'out_of_range' && $settings['alcohol_out_of_range_approval_required'] === '1',
        ];
    }
}
