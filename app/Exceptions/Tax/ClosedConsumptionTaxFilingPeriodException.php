<?php

namespace App\Exceptions\Tax;

use DomainException;

class ClosedConsumptionTaxFilingPeriodException extends DomainException
{
    public static function forDate(string $date): self
    {
        return new self("Consumption tax filing period is already confirmed for invoice date [{$date}].");
    }
}
