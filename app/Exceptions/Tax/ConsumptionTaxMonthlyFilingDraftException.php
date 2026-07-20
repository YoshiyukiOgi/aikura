<?php

namespace App\Exceptions\Tax;

use DomainException;

class ConsumptionTaxMonthlyFilingDraftException extends DomainException
{
    public static function alreadyConfirmed(int $year, int $month): self
    {
        return new self("Consumption tax monthly filing [{$year}-{$month}] is already confirmed.");
    }
}
