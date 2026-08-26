<?php

namespace App\Exceptions\Inventory;

use DomainException;

class StockMonthlyBalanceConfirmationException extends DomainException
{
    public static function emptyReason(): self
    {
        return new self('在庫月次確定理由が必要です。');
    }

    public static function noDraftBalances(int $year, int $month): self
    {
        return new self("{$year}年{$month}月の下書き在庫月次残高がありません。");
    }

    public static function containsNonDraftBalances(int $year, int $month): self
    {
        return new self("{$year}年{$month}月の在庫月次残高に下書き以外の行が含まれています。");
    }
}
