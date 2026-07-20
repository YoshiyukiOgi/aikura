<?php

namespace App\Exceptions\Shipment;

use DomainException;

class ShipmentFlowCancellationException extends DomainException
{
    public static function confirmedShipmentExists(int $instructionId): self
    {
        return new self("出荷指示 [{$instructionId}] は出荷確定済みのため、出荷取消はできません。");
    }
}
