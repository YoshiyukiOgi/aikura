<?php

namespace App\Exceptions\Shipment;

use DomainException;

class ShipmentDraftException extends DomainException
{
    public static function inactiveCustomer(int $customerId): self
    {
        return new self("取引先 [{$customerId}] は無効です。");
    }

    public static function inactiveProduct(int $productId): self
    {
        return new self("商品 [{$productId}] は無効、または販売対象外です。");
    }

    public static function inactiveUnit(int $unitId): self
    {
        return new self("単位 [{$unitId}] は無効です。");
    }

    public static function emptyLines(): self
    {
        return new self('出荷下書きには明細が1件以上必要です。');
    }

    public static function invalidQuantity(string $quantity): self
    {
        return new self("出荷明細数量は0より大きい必要があります: {$quantity}");
    }

    public static function notDraft(int $shipmentHeaderId, string $status): self
    {
        return new self("出荷伝票 [{$shipmentHeaderId}] は下書き状態である必要があります。現在の状態: {$status}");
    }

    public static function pickCancelled(int $shipmentPickId): self
    {
        return new self("ピッキング [{$shipmentPickId}] は取消済みです。");
    }

    public static function pickHasNoLines(int $shipmentPickId): self
    {
        return new self("ピッキング [{$shipmentPickId}] に明細がありません。");
    }

    public static function pickAlreadyConverted(int $shipmentPickId): self
    {
        return new self("ピッキング [{$shipmentPickId}] は既に出荷下書きへ変換済みです。");
    }
}
