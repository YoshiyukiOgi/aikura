<?php

namespace App\Services\Tax;

use Carbon\CarbonImmutable;

class MonthlyConsumptionTaxSummary
{
    public function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly CarbonImmutable $periodStart,
        public readonly CarbonImmutable $periodEnd,
        public readonly int $consumptionTaxCategoryId,
        public readonly string $consumptionTaxCategoryCode,
        public readonly string $consumptionTaxCategoryName,
        public readonly string $consumptionTaxability,
        public readonly ?int $consumptionTaxRateId,
        public readonly ?string $taxRate,
        public readonly ?CarbonImmutable $rateEffectiveFrom,
        public readonly string $taxableAmount,
        public readonly string $taxAmount,
        public readonly string $totalAmount,
        public readonly int $invoiceCount,
        public readonly int $lineCount,
    ) {
    }
}
