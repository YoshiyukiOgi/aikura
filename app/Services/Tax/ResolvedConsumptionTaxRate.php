<?php

namespace App\Services\Tax;

use App\Models\ConsumptionTaxCategory;
use App\Models\ConsumptionTaxRate;

class ResolvedConsumptionTaxRate
{
    public function __construct(
        public readonly ConsumptionTaxCategory $category,
        public readonly ConsumptionTaxRate $rate,
    ) {
    }
}
