<?php

namespace App\Exceptions\Tax;

use DomainException;

class ConsumptionTaxMonthlyFilingConfirmationException extends DomainException
{
    public static function emptyReason(): self
    {
        return new self('Consumption tax monthly filing confirmation reason is required.');
    }

    public static function noDraftFiling(int $year, int $month): self
    {
        return new self("No draft consumption tax monthly filing exists for [{$year}-{$month}].");
    }

    public static function nonDraftFiling(int $year, int $month): self
    {
        return new self("Consumption tax monthly filing [{$year}-{$month}] is not draft.");
    }
}
