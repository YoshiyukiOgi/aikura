<?php

namespace App\Exceptions\Shipment;

use DomainException;

class ShipmentInstructionException extends DomainException
{
    public static function emptyLines(): self
    {
        return new self('Shipment instruction requires at least one line.');
    }

    public static function invalidQuantity(string $quantity): self
    {
        return new self("Shipment instruction line quantity [{$quantity}] must be greater than zero.");
    }

    public static function cancelledSalesOrder(int $salesOrderId): self
    {
        return new self("Sales order [{$salesOrderId}] is cancelled.");
    }

    public static function customerMismatch(int $expectedCustomerId, int $actualCustomerId): self
    {
        return new self('出荷指示には同じ取引先の受注明細だけを選択してください。');
    }

    public static function multipleSalesOrders(): self
    {
        return new self('出荷指示は受注番号ごとに作成してください。複数の受注を同じ出荷指示に含めることはできません。');
    }

    public static function settlementReceivableCategoryMismatch(int $expectedCategoryId, int $actualCategoryId): self
    {
        return new self("Shipment instruction settlement receivable category [{$expectedCategoryId}] does not match sales order category [{$actualCategoryId}].");
    }

    public static function exceedsRemainingQuantity(int $salesOrderLineId, string $remainingQuantity, string $requestedQuantity): self
    {
        return new self("Sales order line [{$salesOrderLineId}] remaining quantity [{$remainingQuantity}] is less than requested instruction quantity [{$requestedQuantity}].");
    }

    public static function duplicateSalesOrderLine(int $salesOrderLineId): self
    {
        return new self("Sales order line [{$salesOrderLineId}] is duplicated in the same shipment instruction.");
    }

    public static function emptyCancellationReason(): self
    {
        return new self('Shipment instruction cancellation reason is required.');
    }

    public static function alreadyCancelled(int $shipmentInstructionId): self
    {
        return new self("Shipment instruction [{$shipmentInstructionId}] is already cancelled.");
    }

    public static function alreadyPicked(int $shipmentInstructionId): self
    {
        return new self("Shipment instruction [{$shipmentInstructionId}] has picked quantity and cannot be cancelled directly.");
    }
}
