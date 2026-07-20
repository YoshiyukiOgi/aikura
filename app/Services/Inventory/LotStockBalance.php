<?php

namespace App\Services\Inventory;

class LotStockBalance
{
    public function __construct(
        public readonly int $productionLotId,
        public readonly int $stockLocationId,
        public readonly int $unitId,
        public readonly string $physicalQuantity,
        public readonly string $reservedQuantity,
        public readonly string $allocatedQuantity,
        public readonly string $availableQuantity,
    ) {
    }
}
