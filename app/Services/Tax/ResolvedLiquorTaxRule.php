<?php

namespace App\Services\Tax;

use App\Models\LiquorTaxCategory;
use App\Models\LiquorTaxRule;

class ResolvedLiquorTaxRule
{
    public function __construct(
        public readonly LiquorTaxCategory $category,
        public readonly LiquorTaxRule $rule,
    ) {
    }
}
