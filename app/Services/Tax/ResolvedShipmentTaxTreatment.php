<?php

namespace App\Services\Tax;

use App\Models\SettlementReceivableCategory;

class ResolvedShipmentTaxTreatment
{
    public function __construct(
        public readonly SettlementReceivableCategory $settlementCategory,
        public readonly string $liquorTaxTreatment,
        public readonly string $consumptionTaxTreatment,
        public readonly string $exportType,
        public readonly bool $requiresReview,
        public readonly bool $requiresEvidence,
    ) {
    }
}
