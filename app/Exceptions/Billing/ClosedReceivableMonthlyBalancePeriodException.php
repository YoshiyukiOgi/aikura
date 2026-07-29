<?php

namespace App\Exceptions\Billing;

use DomainException;

class ClosedReceivableMonthlyBalancePeriodException extends DomainException
{
    public static function forDate(string $date): self
    {
        return new self("{$date}を含む売掛月次残高は締め済みのため操作できません。締めを取り消してから再度実行してください。");
    }
}
