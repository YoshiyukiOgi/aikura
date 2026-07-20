<?php

namespace App\Services\Tax;

use Carbon\CarbonImmutable;

class MonthlyLiquorTaxTransferSummary
{
    /** @param array<int, MonthlyLiquorTaxSourceData> $sources */
    public function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly CarbonImmutable $periodStart,
        public readonly CarbonImmutable $periodEnd,
        public readonly int $liquorTaxCategoryId,
        public readonly string $liquorTaxCategoryCode,
        public readonly string $liquorTaxCategoryName,
        public readonly string $liquorTaxability,
        public readonly ?int $liquorTaxRuleId,
        public readonly ?string $calculationMethod,
        public readonly ?string $taxPerKl,
        public readonly ?string $reductionRate,
        public readonly string $taxTreatment,
        public readonly string $sourceType,
        public readonly string $taxableKl,
        public readonly string $estimatedAmount,
        public readonly string $grossTaxAmount,
        public readonly bool $requiresReview,
        public readonly int $shipmentCount,
        public readonly int $lineCount,
        public readonly array $sources = [],
    ) {
    }
}
