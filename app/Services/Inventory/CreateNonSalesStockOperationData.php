<?php

namespace App\Services\Inventory;

class CreateNonSalesStockOperationData
{
    /**
     * @param array<int, CreateNonSalesStockOperationLineData> $lines
     */
    public function __construct(
        public readonly string $operationType,
        public readonly string $operationDate,
        public readonly string $reason,
        public readonly array $lines,
        public readonly ?int $sourceSalesReturnHeaderId = null,
        public readonly ?string $note = null,
        public readonly bool $alcoholWarningAcknowledged = false,
    ) {
    }
}
