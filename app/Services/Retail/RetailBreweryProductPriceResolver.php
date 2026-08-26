<?php

namespace App\Services\Retail;

use App\Models\ConsumptionTaxRate;
use App\Models\PriceRule;
use App\Models\Product;

class RetailBreweryProductPriceResolver
{
    /**
     * @return array{cost_price: float, selling_price: float, tax_rate: float, has_cost_price: bool, has_selling_price: bool}
     */
    public function resolve(int|Product $breweryProduct): array
    {
        $product = $breweryProduct instanceof Product
            ? $breweryProduct
            : Product::query()->with('consumptionTaxCategory')->find($breweryProduct);

        $breweryProductId = $product?->id ?? (int) $breweryProduct;
        $taxRate = $this->taxRate($product);
        $costPrice = $this->sourcePrice($breweryProductId, ['wholesale_price', 'producer_price']);
        $sellingPrice = $this->sourcePrice($breweryProductId, ['retail_price']);

        return [
            'cost_price' => $costPrice ?? 0.0,
            'selling_price' => $sellingPrice ?? 0.0,
            'tax_rate' => $taxRate,
            'has_cost_price' => $costPrice !== null,
            'has_selling_price' => $sellingPrice !== null,
        ];
    }

    /**
     * @param array<int, string> $priceListCodes
     */
    public function sourcePrice(int $productId, array $priceListCodes): ?float
    {
        foreach ($priceListCodes as $priceListCode) {
            $rule = PriceRule::query()
                ->where('product_id', $productId)
                ->whereNull('customer_id')
                ->where('is_active', true)
                ->whereDate('effective_from', '<=', now()->toDateString())
                ->where(function ($query): void {
                    $query->whereNull('effective_to')
                        ->orWhereDate('effective_to', '>=', now()->toDateString());
                })
                ->whereHas('priceList', fn ($query) => $query->where('code', $priceListCode)->where('is_active', true))
                ->with('priceList:id,code')
                ->orderBy('priority')
                ->latest('effective_from')
                ->first();

            if ($rule) {
                return round((float) $rule->unit_price, 2);
            }
        }

        return null;
    }

    public function taxRate(?Product $product): float
    {
        $category = $product?->consumptionTaxCategory;
        if (! $category) {
            return 0.1000;
        }

        if (! $category->requires_tax_rate) {
            return 0.0;
        }

        $rate = ConsumptionTaxRate::query()
            ->where('consumption_tax_category_id', $category->id)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', now()->toDateString())
            ->where(function ($query): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', now()->toDateString());
            })
            ->latest('effective_from')
            ->first();

        return $rate ? round((float) $rate->rate, 4) : 0.1000;
    }

}
