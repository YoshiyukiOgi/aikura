<?php

namespace App\Exceptions\Pricing;

use DomainException;

class PriceResolutionException extends DomainException
{
    public static function notFound(int $customerId, int $productId, string $date): self
    {
        return new self("取引先 [{$customerId}]・商品 [{$productId}] の {$date} 時点有効単価が見つかりません。");
    }
}

