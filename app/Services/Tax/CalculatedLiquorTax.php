<?php

namespace App\Services\Tax;

use App\Models\LiquorTaxCategory;
use App\Models\LiquorTaxRule;

class CalculatedLiquorTax
{
    public function __construct(
        public readonly LiquorTaxCategory $category,
        public readonly ?LiquorTaxRule $rule,
        public readonly string $taxableKl,
        public readonly string $estimatedAmount,
        public readonly ?string $taxPerKl = null,
    ) {
    }
}
