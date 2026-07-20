<?php

namespace App\Exceptions\Inventory;

use DomainException;

class StockMonthlyBalanceConfirmationException extends DomainException
{
    public static function emptyReason(): self
    {
        return new self('Stock monthly balance confirmation reason is required.');
    }

    public static function noDraftBalances(int $year, int $month): self
    {
        return new self("No draft stock monthly balances exist for [{$year}-{$month}].");
    }

    public static function containsNonDraftBalances(int $year, int $month): self
    {
        return new self("Stock monthly balances for [{$year}-{$month}] contain non-draft rows.");
    }
}
