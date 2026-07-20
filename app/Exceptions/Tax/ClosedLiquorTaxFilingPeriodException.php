<?php

namespace App\Exceptions\Tax;

use DomainException;

class ClosedLiquorTaxFilingPeriodException extends DomainException
{
    public static function forDate(string $date): self
    {
        return new self("Liquor tax filing period is already confirmed for date [{$date}].");
    }
}
