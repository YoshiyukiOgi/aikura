<?php

namespace App\Exceptions\Inventory;

use DomainException;

class ClosedStockPeriodException extends DomainException
{
    public static function forDate(string $movementDate): self
    {
        return new self("{$movementDate}を含む在庫期間は締め済みのため操作できません。締めを取り消してから再度実行してください。");
    }
}
