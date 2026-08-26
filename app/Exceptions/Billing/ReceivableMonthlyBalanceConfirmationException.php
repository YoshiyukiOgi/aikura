<?php

namespace App\Exceptions\Billing;

use DomainException;

class ReceivableMonthlyBalanceConfirmationException extends DomainException
{
    public static function noDraftBalances(int $year, int $month): self
    {
        return new self("{$year}年{$month}月の下書き売掛月次残高がありません。");
    }

    public static function containsNonDraftBalances(int $year, int $month): self
    {
        return new self("{$year}年{$month}月の売掛月次残高に下書き以外の行が含まれています。");
    }

    public static function emptyReason(): self
    {
        return new self('売掛月次確定理由が必要です。');
    }
}
