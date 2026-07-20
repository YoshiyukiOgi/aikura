<?php

namespace App\Services\Billing;

class CreateSalesReturnLineData
{
    /**
     * @param  array<int, CreateSalesReturnLineLotData>  $lots
     */
    public function __construct(
        public readonly int $sourceInvoiceLineId,
        public readonly string $quantity,
        public readonly string $stockAction = 'return_dedicated_stock',
        public readonly string $liquorTaxReturnTreatment = 'review',
        public readonly ?string $liquorTaxReturnReason = null,
        public readonly ?int $liquorTaxReviewedBy = null,
        public readonly ?int $stockLocationId = null,
        public readonly ?int $productionLotId = null,
        public readonly ?string $lotCode = null,
        public readonly ?string $reason = null,
        public readonly ?string $note = null,
        public readonly array $lots = [],
    ) {}
}
