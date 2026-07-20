<?php

namespace App\Services\Pricing;

use App\Models\PriceList;
use App\Models\PriceRule;

class ResolvedPrice
{
    public function __construct(
        public readonly string $unitPrice,
        public readonly int $priceListId,
        public readonly int $priceRuleId,
        public readonly int $unitId,
        public readonly string $source,
        public readonly string $reason,
        public readonly PriceList $priceList,
        public readonly PriceRule $priceRule,
    ) {
    }
}

