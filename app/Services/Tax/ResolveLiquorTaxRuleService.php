<?php

namespace App\Services\Tax;

use App\Exceptions\Tax\LiquorTaxRuleResolutionException;
use App\Models\LiquorTaxCategory;
use App\Models\LiquorTaxRule;

class ResolveLiquorTaxRuleService
{
    public function resolve(LiquorTaxCategory|string $category, string $date, ?string $alcoholPercentage = null): ResolvedLiquorTaxRule
    {
        $category = $category instanceof LiquorTaxCategory
            ? $category
            : LiquorTaxCategory::query()->where('code', $category)->firstOrFail();

        if ($category->taxability !== 'taxable') {
            throw LiquorTaxRuleResolutionException::categoryIsNotTaxable($category->code);
        }

        $rule = LiquorTaxRule::query()
            ->where('liquor_tax_category_id', $category->id)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            })
            ->where(function ($query) use ($alcoholPercentage): void {
                if ($alcoholPercentage === null) {
                    $query
                        ->whereNull('alcohol_percentage_min')
                        ->whereNull('alcohol_percentage_max');

                    return;
                }

                $query
                    ->where(function ($query) use ($alcoholPercentage): void {
                        $query
                            ->whereNull('alcohol_percentage_min')
                            ->orWhere('alcohol_percentage_min', '<=', $alcoholPercentage);
                    })
                    ->where(function ($query) use ($alcoholPercentage): void {
                        $query
                            ->whereNull('alcohol_percentage_max')
                            ->orWhere('alcohol_percentage_max', '>=', $alcoholPercentage);
                    });
            })
            ->orderByDesc('effective_from')
            ->orderByRaw('CASE WHEN alcohol_percentage_min IS NULL AND alcohol_percentage_max IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByDesc('alcohol_percentage_min')
            ->first();

        if ($rule === null) {
            throw LiquorTaxRuleResolutionException::missingRule($category->code, $date, $alcoholPercentage);
        }

        return new ResolvedLiquorTaxRule($category, $rule);
    }
}
