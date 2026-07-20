<?php

namespace App\Exceptions\Billing;

use DomainException;

class ReceivableMonthlyBalanceDraftException extends DomainException
{
    public static function alreadyConfirmed(int $year, int $month): self
    {
        return new self("Receivable monthly balances [{$year}-{$month}] contain non-draft rows.");
    }
}
