<?php

namespace App\Services\Tax;

use App\Models\ConsumptionTaxCategory;
use App\Models\Product;

class ResolveProductConsumptionTaxCategoryService
{
    public function resolve(Product $product): ConsumptionTaxCategory
    {
        if ($product->consumptionTaxCategory !== null) {
            return $product->consumptionTaxCategory;
        }

        return ConsumptionTaxCategory::query()
            ->where('code', $this->defaultCategoryCode($product))
            ->firstOrFail();
    }

    public function assignDefault(Product $product): Product
    {
        $category = $this->resolve($product);

        $product->forceFill([
            'consumption_tax_category_id' => $category->id,
        ])->save();

        return $product->refresh();
    }

    private function defaultCategoryCode(Product $product): string
    {
        if ($product->is_alcohol || $product->product_type === 'sake') {
            return 'taxable_standard';
        }

        return match ($product->product_type) {
            'food', 'kasu' => 'taxable_reduced',
            default => 'taxable_standard',
        };
    }
}
