<?php

namespace App\Services\Billing;

class CreateSalesReturnLineLotData
{
    public function __construct(
        public readonly int $productionLotId,
        public readonly string $quantity,
        public readonly ?int $stockLocationId = null,
        public readonly ?string $lotCode = null,
        public readonly ?string $note = null,
    ) {
    }
}
