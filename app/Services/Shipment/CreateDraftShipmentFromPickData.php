<?php

namespace App\Services\Shipment;

class CreateDraftShipmentFromPickData
{
    public function __construct(
        public readonly int $shipmentPickId,
        public readonly ?string $documentDate = null,
        public readonly ?string $billingTargetDate = null,
        public readonly ?string $liquorTaxTransferDate = null,
        public readonly ?string $note = null,
        public readonly ?string $reason = null,
    ) {
    }
}
