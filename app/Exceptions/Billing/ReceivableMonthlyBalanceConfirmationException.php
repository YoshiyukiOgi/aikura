<?php

namespace App\Exceptions\Billing;

use DomainException;

class ReceivableMonthlyBalanceConfirmationException extends DomainException
{
    public static function noDraftBalances(int $year, int $month): self
    {
        return new self("Receivable monthly balances [{$year}-{$month}] do not exist.");
    }

    public static function containsNonDraftBalances(int $year, int $month): self
    {
        return new self("Receivable monthly balances [{$year}-{$month}] contain non-draft rows.");
    }

    public static function emptyReason(): self
    {
        return new self('Receivable monthly balance confirmation reason is required.');
    }
}
