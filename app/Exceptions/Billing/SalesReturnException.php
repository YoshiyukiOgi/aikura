<?php

namespace App\Exceptions\Billing;

use RuntimeException;

class SalesReturnException extends RuntimeException
{
    public static function emptyReason(): self
    {
        return new self('Sales return reason is required.');
    }

    public static function emptyLines(): self
    {
        return new self('Sales return requires at least one line.');
    }

    public static function sourceInvoiceNotConfirmed(int $invoiceId, string $status): self
    {
        return new self("Source invoice [{$invoiceId}] must be confirmed. Current status: {$status}.");
    }

    public static function customerMismatch(int $customerId, int $invoiceCustomerId): self
    {
        return new self("Sales return customer [{$customerId}] does not match source invoice customer [{$invoiceCustomerId}].");
    }

    public static function quantityMustBePositive(): self
    {
        return new self('Sales return quantity must be positive.');
    }

    public static function quantityExceedsRemaining(string $requested, string $remaining): self
    {
        return new self("Sales return quantity [{$requested}] exceeds remaining returnable quantity [{$remaining}].");
    }

    public static function stockLocationRequired(string $stockAction): self
    {
        return new self("Stock location is required for sales return stock action [{$stockAction}].");
    }

    public static function invalidStockAction(string $stockAction): self
    {
        return new self("Sales return stock action [{$stockAction}] is not supported.");
    }

    public static function productionLotRequiredForReturnStock(): self
    {
        return new self('Production lot allocation is required when returning goods to regular sales stock.');
    }

    public static function productionLotNotInSourceShipment(int $productionLotId): self
    {
        return new self("Production lot [{$productionLotId}] was not used by the source shipment line.");
    }

    public static function lotQuantityMismatch(string $returnQuantity, string $lotQuantity): self
    {
        return new self("Sales return lot quantity total [{$lotQuantity}] must match return quantity [{$returnQuantity}].");
    }

    public static function lotQuantityExceedsSource(int $productionLotId, string $requested, string $sourceQuantity): self
    {
        return new self("Sales return lot quantity [{$requested}] exceeds source shipment lot quantity [{$sourceQuantity}] for production lot [{$productionLotId}].");
    }

    public static function emptyCancelReason(): self
    {
        return new self('Sales return cancellation reason is required.');
    }

    public static function alreadyCancelled(int $salesReturnId): self
    {
        return new self("Sales return [{$salesReturnId}] is already cancelled.");
    }
}
