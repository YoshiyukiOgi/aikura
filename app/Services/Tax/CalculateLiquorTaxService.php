<?php

namespace App\Services\Tax;

use App\Models\LiquorTaxCategory;
use App\Models\Product;
use App\Models\ProductionLot;

class CalculateLiquorTaxService
{
    public function __construct(
        private readonly ResolveLiquorTaxRuleService $resolveLiquorTaxRuleService,
        private readonly TaxRoundingService $taxRoundingService,
    ) {
    }

    public function calculate(Product $product, string $quantity, string $date): CalculatedLiquorTax
    {
        return $this->calculateWithValues($product, $quantity, $date, $product->alcohol_percentage, $product->capacity_value, $product->capacityUnit?->code);
    }

    public function calculateForLot(Product $product, ProductionLot $lot, string $quantity, string $date): CalculatedLiquorTax
    {
        return $this->calculateWithValues($product, $quantity, $date, $lot->alcohol_percentage, $lot->capacity_value, $lot->capacityUnit?->code);
    }

    public function volumeKl(Product $product, string $quantity, ?ProductionLot $lot = null): string
    {
        return $this->calculateTaxableKl(
            $quantity,
            $lot?->capacity_value ?? $product->capacity_value,
            $lot?->capacityUnit?->code ?? $product->capacityUnit?->code,
        );
    }

    private function calculateWithValues(Product $product, string $quantity, string $date, ?string $alcoholPercentage, ?string $capacityValue, ?string $capacityUnitCode): CalculatedLiquorTax
    {
        $category = $this->resolveCategory($product);

        if ($category->taxability !== 'taxable') {
            return new CalculatedLiquorTax(
                category: $category,
                rule: null,
                taxableKl: '0.000000',
                estimatedAmount: '0.00',
                taxPerKl: null,
            );
        }

        $rule = $this->resolveLiquorTaxRuleService
            ->resolve($category, $date, $alcoholPercentage)
            ->rule;

        $taxableKl = $this->calculateTaxableKl($quantity, $capacityValue, $capacityUnitCode);
        $taxPerKl = $this->taxPerKl($rule->calculation_method, (string) $rule->tax_per_kl, $alcoholPercentage, $rule->base_alcohol_percentage, $rule->additional_tax_per_kl_per_percent);
        $baseTax = bcmul($taxableKl, $taxPerKl, 8);
        $reductionMultiplier = bcsub('1.0000', $rule->reduction_rate, 4);
        $reducedTax = bcmul($baseTax, $reductionMultiplier, 8);

        return new CalculatedLiquorTax(
            category: $category,
            rule: $rule,
            taxableKl: $taxableKl,
            estimatedAmount: $this->taxRoundingService->round($reducedTax, 'round'),
            taxPerKl: $taxPerKl,
        );
    }

    private function resolveCategory(Product $product): LiquorTaxCategory
    {
        $code = $product->sakeDetail?->liquor_tax_category_code;

        if ($code === null || $code === '') {
            $code = $product->is_alcohol ? 'seishu' : 'non_liquor';
        }

        return LiquorTaxCategory::query()->where('code', $code)->firstOrFail();
    }

    private function calculateTaxableKl(string $quantity, ?string $capacityValue, ?string $capacityUnitCode): string
    {
        $capacity = (string) ($capacityValue ?? '0');

        $liters = match ($capacityUnitCode) {
            'milliliter' => bcdiv($capacity, '1000', 8),
            'liter' => $capacity,
            default => '0',
        };

        $totalLiters = bcmul($quantity, $liters, 8);

        return bcdiv($totalLiters, '1000', 6);
    }

    private function taxPerKl(string $method, string $baseTax, ?string $alcoholPercentage, ?string $baseAlcoholPercentage, ?string $additionalTax): string
    {
        if ($method === 'fixed_per_kl') {
            return bcadd($baseTax, '0', 4);
        }

        if ($method !== 'per_degree' || $alcoholPercentage === null || $baseAlcoholPercentage === null || $additionalTax === null) {
            throw new \DomainException("未対応または設定不足の酒税計算方式です: {$method}");
        }

        $wholeDegrees = (string) floor((float) $alcoholPercentage);
        $additionalDegrees = max(0, (int) $wholeDegrees - (int) floor((float) $baseAlcoholPercentage));

        return bcadd($baseTax, bcmul((string) $additionalDegrees, (string) $additionalTax, 4), 4);
    }
}
