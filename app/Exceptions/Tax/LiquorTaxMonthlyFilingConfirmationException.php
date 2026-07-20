<?php

namespace App\Exceptions\Tax;

use DomainException;

class LiquorTaxMonthlyFilingConfirmationException extends DomainException
{
    public static function emptyReason(): self
    {
        return new self('Liquor tax monthly filing confirmation reason is required.');
    }

    public static function noDraftFiling(int $year, int $month): self
    {
        return new self("No draft liquor tax monthly filing exists for [{$year}-{$month}].");
    }

    public static function nonDraftFiling(int $year, int $month): self
    {
        return new self("Liquor tax monthly filing [{$year}-{$month}] is not draft.");
    }
}
