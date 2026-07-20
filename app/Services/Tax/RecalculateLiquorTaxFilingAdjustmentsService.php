<?php

namespace App\Services\Tax;

use App\Models\LiquorTaxMonthlyFiling;

class RecalculateLiquorTaxFilingAdjustmentsService
{
    public function recalculate(LiquorTaxMonthlyFiling $filing): LiquorTaxMonthlyFiling
    {
        $quantity = (string) $filing->adjustments()->where('status', 'active')->sum('taxable_kl_adjustment');
        $amount = (string) $filing->adjustments()->where('status', 'active')->sum('tax_amount_adjustment');
        $count = $filing->adjustments()->where('status', 'active')->count();
        $base = bcsub(bcsub((string) $filing->total_gross_tax_amount, (string) $filing->total_relief_amount, 2), (string) $filing->total_deduction_amount, 2);
        $beforeRounding = bcadd($base, $amount, 2);
        $payable = bccomp($beforeRounding, '0', 2) === 1 ? bcmul(bcdiv($beforeRounding, '100', 0), '100', 2) : '0.00';

        $filing->forceFill([
            'total_taxable_kl' => bcadd((string) $filing->lines()->where('tax_treatment', 'taxable')->sum('taxable_kl'), $quantity, 6),
            'total_adjustment_taxable_kl' => bcadd($quantity, '0', 6),
            'total_adjustment_amount' => bcadd($amount, '0', 2),
            'adjustment_count' => $count,
            'net_payable_amount' => $payable,
            'total_estimated_amount' => $payable,
        ])->save();

        return $filing->refresh();
    }
}
