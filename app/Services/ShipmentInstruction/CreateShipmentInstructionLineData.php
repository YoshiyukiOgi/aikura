<?php

namespace App\Services\ShipmentInstruction;

class CreateShipmentInstructionLineData
{
    public function __construct(
        public readonly int $salesOrderLineId,
        public readonly string $quantity,
        public readonly ?string $note = null,
    ) {
    }
}
