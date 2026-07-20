<?php

namespace App\Services\Inventory;

class CreateNonSalesStockOperationLineData
{
    public function __construct(
        public readonly int $productionLotId,
        public readonly int $stockLocationId,
        public readonly string $quantity,
        public readonly ?int $productId = null,
        public readonly ?int $sourceSalesReturnLineId = null,
        public readonly ?string $lotCode = null,
        public readonly ?string $reason = null,
        public readonly ?string $note = null,
    ) {
    }
}
