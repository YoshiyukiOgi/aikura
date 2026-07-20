<?php

namespace App\Exceptions\Billing;

use DomainException;

class ClosedReceivableMonthlyBalancePeriodException extends DomainException
{
    public static function forDate(string $date): self
    {
        return new self("Receivable monthly balance period for [{$date}] is already confirmed or closed.");
    }
}
