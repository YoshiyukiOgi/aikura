<?php

namespace App\Services\Tax;

class MonthlyLiquorTaxSourceData
{
    public function __construct(
        public readonly string $sourceType,
        public readonly int $sourceHeaderId,
        public readonly int $sourceLineId,
        public readonly ?string $sourceDocumentNumber,
        public readonly string $sourceDate,
        public readonly string $taxTreatment,
        public readonly string $quantity,
        public readonly string $taxableKl,
        public readonly string $grossTaxAmount,
        public readonly bool $requiresReview = false,
        public readonly ?string $reviewReason = null,
        public readonly ?string $evidenceStatus = null,
        public readonly ?string $evidenceReference = null,
    ) {}
}
