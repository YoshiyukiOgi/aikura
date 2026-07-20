<?php

namespace App\Exceptions\Inventory;

use DomainException;

class InventoryAdjustmentException extends DomainException
{
    public static function emptyReason(): self
    {
        return new self('Inventory adjustment reason is required.');
    }

    public static function zeroQuantity(): self
    {
        return new self('Inventory adjustment quantity must not be zero.');
    }

    public static function inactiveProduct(int $productId): self
    {
        return new self("Product [{$productId}] is inactive or not inventory managed.");
    }

    public static function inactiveStockLocation(int $stockLocationId): self
    {
        return new self("Stock location [{$stockLocationId}] is inactive or not inventory managed.");
    }

    public static function inactiveUnit(int $unitId): self
    {
        return new self("Unit [{$unitId}] is inactive.");
    }
}
