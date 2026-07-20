<?php

namespace App\Services\Tax;

use App\Exceptions\Tax\TaxRateResolutionException;
use App\Models\ConsumptionTaxCategory;
use App\Models\ConsumptionTaxRate;

class ResolveConsumptionTaxRateService
{
    public function resolve(ConsumptionTaxCategory|string $category, string $date): ResolvedConsumptionTaxRate
    {
        $category = $category instanceof ConsumptionTaxCategory
            ? $category
            : ConsumptionTaxCategory::query()->where('code', $category)->firstOrFail();

        if (! $category->requires_tax_rate) {
            throw TaxRateResolutionException::categoryDoesNotRequireRate($category->code);
        }

        $rate = ConsumptionTaxRate::query()
            ->where('consumption_tax_category_id', $category->id)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->first();

        if ($rate === null) {
            throw TaxRateResolutionException::missingRate($category->code, $date);
        }

        return new ResolvedConsumptionTaxRate($category, $rate);
    }
}
