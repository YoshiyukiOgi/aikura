<?php

namespace App\Exceptions\Shipment;

use DomainException;

class ShipmentLotAllocationException extends DomainException
{
    public static function notDraft(int $shipmentHeaderId, string $status): self
    {
        return new self("出荷伝票 [{$shipmentHeaderId}] は下書き状態でないとロット割当できません。現在の状態: {$status}");
    }

    public static function invalidQuantity(string $quantity): self
    {
        return new self("ロット割当数量は0より大きい必要があります: {$quantity}");
    }

    public static function inactiveLot(int $productionLotId): self
    {
        return new self("ロット [{$productionLotId}] は無効です。");
    }

    public static function lotIsNotLinkedToProduct(int $productionLotId, int $productId): self
    {
        return new self("ロット [{$productionLotId}] は商品 [{$productId}] の出荷対象として紐づいていません。");
    }

    public static function exceedsLineQuantity(int $shipmentLineId, string $lineQuantity, string $allocatedQuantity): self
    {
        return new self("出荷明細 [{$shipmentLineId}] の数量 {$lineQuantity} が、割当数量 {$allocatedQuantity} を下回っています。");
    }

    public static function insufficientLotStock(int $productionLotId, string $availableQuantity, string $requestedQuantity): self
    {
        return new self("ロット [{$productionLotId}] の使用可能数量 {$availableQuantity} が、要求数量 {$requestedQuantity} を下回っています。");
    }
}
