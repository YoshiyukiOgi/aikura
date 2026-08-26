<?php

namespace App\Exceptions\Inventory;

use DomainException;

class InventoryAdjustmentException extends DomainException
{
    public static function emptyReason(): self
    {
        return new self('在庫調整理由が必要です。');
    }

    public static function zeroQuantity(): self
    {
        return new self('在庫調整数量に0は指定できません。');
    }

    public static function inactiveProduct(int $productId): self
    {
        return new self("商品 [{$productId}] は無効、または在庫管理対象外です。");
    }

    public static function inactiveStockLocation(int $stockLocationId): self
    {
        return new self("在庫場所 [{$stockLocationId}] は無効、または在庫管理対象外です。");
    }

    public static function inactiveUnit(int $unitId): self
    {
        return new self("単位 [{$unitId}] は無効です。");
    }
}
