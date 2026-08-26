<?php

namespace App\Services\SalesOrder;

class CreateSalesOrderData
{
    /**
     * @param array<int, CreateSalesOrderLineData> $lines
     */
    public function __construct(
        public readonly int $customerId,
        public readonly string $orderDate,
        public readonly ?int $settlementReceivableCategoryId = null,
        public readonly ?string $requestedShipmentDate = null,
        public readonly ?string $requestedDeliveryDate = null,
        public readonly ?string $billingTargetDate = null,
        public readonly ?string $customerOrderNumber = null,
        public readonly ?string $sourceType = null,
        public readonly ?string $sourceReference = null,
        public readonly ?string $note = null,
        public readonly ?string $workNote = null,
        public readonly ?string $reason = null,
        public readonly bool $applyPricing = false,
        public readonly bool $awaitingShipmentInstruction = false,
        public readonly bool $allowNegativeLines = false,
        public readonly array $lines = [],
    ) {
    }
}
