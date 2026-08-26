<?php

namespace App\Exceptions\Shipment;

use DomainException;

class ShipmentInstructionException extends DomainException
{
    public static function emptyLines(): self
    {
        return new self('出荷指示には明細が1件以上必要です。');
    }

    public static function invalidQuantity(string $quantity): self
    {
        return new self("出荷指示明細数量は0より大きい必要があります: {$quantity}");
    }

    public static function cancelledSalesOrder(int $salesOrderId): self
    {
        return new self("受注 [{$salesOrderId}] は取消済みです。");
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
        return new self("出荷指示の精算売掛区分 [{$expectedCategoryId}] と受注の区分 [{$actualCategoryId}] が一致しません。");
    }

    public static function exceedsRemainingQuantity(int $salesOrderLineId, string $remainingQuantity, string $requestedQuantity): self
    {
        return new self("受注明細 [{$salesOrderLineId}] の残数量 {$remainingQuantity} が、要求した指示数量 {$requestedQuantity} を下回っています。");
    }

    public static function duplicateSalesOrderLine(int $salesOrderLineId): self
    {
        return new self("受注明細 [{$salesOrderLineId}] が同じ出荷指示内で重複しています。");
    }

    public static function emptyCancellationReason(): self
    {
        return new self('出荷指示取消理由が必要です。');
    }

    public static function alreadyCancelled(int $shipmentInstructionId): self
    {
        return new self("出荷指示 [{$shipmentInstructionId}] は既に取消済みです。");
    }

    public static function alreadyPicked(int $shipmentInstructionId): self
    {
        return new self("出荷指示 [{$shipmentInstructionId}] にはピッキング済数量があるため、直接取消できません。");
    }
}
