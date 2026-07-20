<?php

namespace App\Services\ShipmentInstruction;

class CreateShipmentInstructionData
{
    /**
     * @param array<int, CreateShipmentInstructionLineData> $lines
     */
    public function __construct(
        public readonly string $instructionDate,
        public readonly ?string $scheduledShipmentDate = null,
        public readonly ?int $stockLocationId = null,
        public readonly ?string $note = null,
        public readonly ?string $reason = null,
        public readonly array $lines = [],
    ) {
    }
}
