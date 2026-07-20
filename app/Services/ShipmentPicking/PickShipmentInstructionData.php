<?php

namespace App\Services\ShipmentPicking;

class PickShipmentInstructionData
{
    /**
     * @param array<int, PickShipmentInstructionLineData> $lines
     */
    public function __construct(
        public readonly int $shipmentInstructionId,
        public readonly string $pickDate,
        public readonly ?int $stockLocationId = null,
        public readonly ?string $note = null,
        public readonly ?string $reason = null,
        public readonly array $lines = [],
    ) {
    }
}
