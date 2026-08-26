<?php

namespace App\Exceptions\Billing;

use DomainException;

class ReceivableMonthlyBalanceDraftException extends DomainException
{
    public static function alreadyConfirmed(int $year, int $month): self
    {
        return new self("{$year}年{$month}月の売掛月次残高に下書き以外の行が含まれています。");
    }
}
