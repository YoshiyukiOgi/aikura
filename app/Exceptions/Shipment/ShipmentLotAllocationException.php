<?php

namespace App\Exceptions\Shipment;

use DomainException;

class ShipmentLotAllocationException extends DomainException
{
    public static function notDraft(int $shipmentHeaderId, string $status): self
    {
        return new self("Shipment [{$shipmentHeaderId}] must be draft to allocate lots, current status is [{$status}].");
    }

    public static function invalidQuantity(string $quantity): self
    {
        return new self("Shipment lot allocation quantity must be greater than zero, [{$quantity}] given.");
    }

    public static function inactiveLot(int $productionLotId): self
    {
        return new self("Production lot [{$productionLotId}] is not active.");
    }

    public static function lotIsNotLinkedToProduct(int $productionLotId, int $productId): self
    {
        return new self("Production lot [{$productionLotId}] is not linked to product [{$productId}] for shipment.");
    }

    public static function exceedsLineQuantity(int $shipmentLineId, string $lineQuantity, string $allocatedQuantity): self
    {
        return new self("Shipment line [{$shipmentLineId}] quantity [{$lineQuantity}] is less than allocated quantity [{$allocatedQuantity}].");
    }

    public static function insufficientLotStock(int $productionLotId, string $availableQuantity, string $requestedQuantity): self
    {
        return new self("Production lot [{$productionLotId}] available quantity [{$availableQuantity}] is less than requested quantity [{$requestedQuantity}].");
    }
}
