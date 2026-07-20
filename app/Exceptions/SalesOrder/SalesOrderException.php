<?php

namespace App\Exceptions\SalesOrder;

use DomainException;

class SalesOrderException extends DomainException
{
    public static function emptyLines(): self
    {
        return new self('Sales order requires at least one line.');
    }

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

    public static function invalidQuantity(string $quantity): self
    {
        return new self("Sales order line quantity [{$quantity}] must be greater than zero.");
    }

    public static function invalidUnitPrice(string $unitPrice): self
    {
        return new self("Sales order line unit price [{$unitPrice}] must be greater than zero.");
    }

    public static function notPriceEditable(int $salesOrderId, string $status): self
    {
        return new self("Sales order [{$salesOrderId}] with status [{$status}] cannot be priced.");
    }

    public static function notEditable(int $salesOrderId, string $status): self
    {
        return new self("Sales order [{$salesOrderId}] with status [{$status}] cannot be edited.");
    }

    public static function lineDoesNotBelong(int $lineId, int $salesOrderId): self
    {
        return new self("Sales order line [{$lineId}] does not belong to sales order [{$salesOrderId}].");
    }

    public static function instructedLineCannotChange(int $lineId): self
    {
        return new self("Sales order line [{$lineId}] already has shipment instruction quantity. The product and unit cannot be changed.");
    }

    public static function quantityBelowInstructed(int $lineId, string $instructedQuantity): self
    {
        return new self("Sales order line [{$lineId}] cannot be reduced below instructed quantity [{$instructedQuantity}].");
    }

    public static function instructedLineCannotDelete(int $lineId): self
    {
        return new self("Sales order line [{$lineId}] already has shipment instruction quantity and cannot be deleted.");
    }

    public static function emptyCancellationReason(): self
    {
        return new self('Sales order cancellation reason is required.');
    }

    public static function alreadyCancelled(int $salesOrderId): self
    {
        return new self("Sales order [{$salesOrderId}] is already cancelled.");
    }

    public static function alreadyInstructed(int $salesOrderId): self
    {
        return new self("Sales order [{$salesOrderId}] has shipment instruction quantity and cannot be cancelled directly.");
    }
}
