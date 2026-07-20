<?php

namespace App\Exceptions\Billing;

use DomainException;

class ReceivableMonthlyBalanceCloseException extends DomainException
{
    public static function noConfirmedBalances(int $year, int $month): self
    {
        return new self("Receivable monthly balances [{$year}-{$month}] do not exist.");
    }

    public static function containsNonConfirmedBalances(int $year, int $month): self
    {
        return new self("Receivable monthly balances [{$year}-{$month}] contain non-confirmed rows.");
    }

    public static function emptyReason(): self
    {
        return new self('Receivable monthly balance close reason is required.');
    }
}
