<?php

namespace App\Exceptions\Inventory;

use RuntimeException;

class NonSalesStockOperationException extends RuntimeException
{
    public static function emptyReason(): self
    {
        return new self('Non-sales stock operation reason is required.');
    }

    public static function emptyLines(): self
    {
        return new self('Non-sales stock operation requires at least one line.');
    }

    public static function zeroQuantity(): self
    {
        return new self('Non-sales stock operation quantity must not be zero.');
    }

    public static function inactiveProduct(int $productId): self
    {
        return new self("Product [{$productId}] is inactive or not inventory managed.");
    }

    public static function inactiveStockLocation(int $stockLocationId): self
    {
        return new self("Stock location [{$stockLocationId}] is inactive or not inventory managed.");
    }
}
