<?php

namespace App\Exceptions\Shipment;

use DomainException;

class ShipmentConfirmationException extends DomainException
{
    public static function notDraft(int $shipmentHeaderId, string $status): self
    {
        return new self("Shipment [{$shipmentHeaderId}] must be draft to confirm, current status is [{$status}].");
    }

    public static function noLines(int $shipmentHeaderId): self
    {
        return new self("Shipment [{$shipmentHeaderId}] has no lines.");
    }

    public static function lineHasNoDraftPrice(int $shipmentLineId): self
    {
        return new self("Shipment line [{$shipmentLineId}] has no draft price.");
    }

    public static function instructionNotPicked(int $shipmentHeaderId): self
    {
        return new self("出荷伝票 [{$shipmentHeaderId}] はピッキング完了後に出荷確定できます。");
    }

    public static function lineLotAllocationIncomplete(int $shipmentLineId, string $lineQuantity, string $allocatedQuantity): self
    {
        return new self("Shipment line [{$shipmentLineId}] quantity [{$lineQuantity}] does not match allocated lot quantity [{$allocatedQuantity}].");
    }
}
