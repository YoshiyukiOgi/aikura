<?php

namespace App\Services\ShipmentPicking;

class PickShipmentInstructionLineData
{
    public function __construct(
        public readonly int $shipmentInstructionLineId,
        public readonly string $quantity,
        public readonly ?string $note = null,
    ) {
    }
}
