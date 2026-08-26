<?php

namespace App\Exceptions\Billing;

use DomainException;

class ReceivableMonthlyBalanceCloseException extends DomainException
{
    public static function noConfirmedBalances(int $year, int $month): self
    {
        return new self("{$year}年{$month}月の確定済み売掛月次残高がありません。");
    }

    public static function containsNonConfirmedBalances(int $year, int $month): self
    {
        return new self("{$year}年{$month}月の売掛月次残高に未確定の行が含まれています。");
    }

    public static function emptyReason(): self
    {
        return new self('売掛月次締め理由が必要です。');
    }
}
