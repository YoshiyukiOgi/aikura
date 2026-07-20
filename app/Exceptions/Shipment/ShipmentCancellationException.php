<?php

namespace App\Exceptions\Shipment;

use DomainException;

class ShipmentCancellationException extends DomainException
{
    public static function alreadyInvoiced(int $shipmentHeaderId): self
    {
        return new self("出荷伝票 [{$shipmentHeaderId}] は請求書に含まれているため取り消せません。");
    }
    public static function emptyReason(): self
    {
        return new self('Shipment cancellation reason is required.');
    }
}

