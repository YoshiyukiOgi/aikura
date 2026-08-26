<?php

namespace App\Exceptions\Inventory;

use RuntimeException;

class NonSalesStockOperationException extends RuntimeException
{
    public static function emptyReason(): self
    {
        return new self('販売外在庫出入の理由が必要です。');
    }

    public static function emptyLines(): self
    {
        return new self('販売外在庫出入には明細が1件以上必要です。');
    }

    public static function zeroQuantity(): self
    {
        return new self('販売外在庫出入の数量に0は指定できません。');
    }

    public static function inactiveProduct(int $productId): self
    {
        return new self("商品 [{$productId}] は無効、または在庫管理対象外です。");
    }

    public static function inactiveStockLocation(int $stockLocationId): self
    {
        return new self("在庫場所 [{$stockLocationId}] は無効、または在庫管理対象外です。");
    }
}
