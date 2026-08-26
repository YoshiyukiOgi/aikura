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
        public readonly string $effectiveFrom,
        public readonly ?string $previousUnitPrice,
        public readonly ?string $changeNotice,
        public readonly PriceList $priceList,
        public readonly PriceRule $priceRule,
    ) {}

    /** @return array<string, mixed> */
    public function salesOrderLineAttributes(): array
    {
        return [
            'unit_price' => $this->unitPrice,
            'price_list_id' => $this->priceListId,
            'price_rule_id' => $this->priceRuleId,
            'price_source' => $this->source,
            'price_reason' => $this->reason,
            'price_effective_from' => $this->effectiveFrom,
            'previous_unit_price' => $this->previousUnitPrice,
            'price_change_notice' => $this->changeNotice,
            'priced_at' => now(),
        ];
    }
}
