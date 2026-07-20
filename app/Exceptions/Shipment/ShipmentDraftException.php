<?php

namespace App\Exceptions\Shipment;

use DomainException;

class ShipmentDraftException extends DomainException
{
    public static function inactiveCustomer(int $customerId): self
    {
        return new self("Customer [{$customerId}] is inactive.");
    }

    public static function inactiveProduct(int $productId): self
    {
        return new self("Product [{$productId}] is inactive or not available for sales.");
    }

    public static function inactiveUnit(int $unitId): self
    {
        return new self("Unit [{$unitId}] is inactive.");
    }

    public static function emptyLines(): self
    {
        return new self('Shipment draft requires at least one line.');
    }

    public static function invalidQuantity(string $quantity): self
    {
        return new self("Shipment line quantity [{$quantity}] must be greater than zero.");
    }

    public static function notDraft(int $shipmentHeaderId, string $status): self
    {
        return new self("Shipment [{$shipmentHeaderId}] must be draft, current status is [{$status}].");
    }

    public static function pickCancelled(int $shipmentPickId): self
    {
        return new self("Shipment pick [{$shipmentPickId}] is cancelled.");
    }

    public static function pickHasNoLines(int $shipmentPickId): self
    {
        return new self("Shipment pick [{$shipmentPickId}] has no lines.");
    }

    public static function pickAlreadyConverted(int $shipmentPickId): self
    {
        return new self("Shipment pick [{$shipmentPickId}] has already been converted to a shipment draft.");
    }
}
