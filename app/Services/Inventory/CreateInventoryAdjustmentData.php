<?php

namespace App\Services\Inventory;

class CreateInventoryAdjustmentData
{
    public function __construct(
        public readonly int $productionLotId,
        public readonly int $stockLocationId,
        public readonly string $quantity,
        public readonly string $movementDate,
        public readonly string $reason,
        public readonly ?string $lotCode = null,
        public readonly ?string $sourceDocumentNumber = null,
        public readonly ?string $note = null,
    ) {
    }
}
