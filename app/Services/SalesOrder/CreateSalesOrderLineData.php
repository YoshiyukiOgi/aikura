<?php

namespace App\Services\SalesOrder;

class CreateSalesOrderLineData
{
    public function __construct(
        public readonly int $productId,
        public readonly string $quantity,
        public readonly int $unitId,
        public readonly ?string $note = null,
    ) {
    }
}
