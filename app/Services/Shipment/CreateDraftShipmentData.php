<?php

namespace App\Services\Shipment;

class CreateDraftShipmentData
{
    /**
     * @param array<int, CreateDraftShipmentLineData> $lines
     */
    public function __construct(
        public readonly int $customerId,
        public readonly string $documentDate,
        public readonly ?string $orderDate = null,
        public readonly ?string $scheduledShipmentDate = null,
        public readonly ?string $billingTargetDate = null,
        public readonly ?string $liquorTaxTransferDate = null,
        public readonly ?string $note = null,
        public readonly ?string $reason = null,
        public readonly array $lines = [],
    ) {
    }
}

