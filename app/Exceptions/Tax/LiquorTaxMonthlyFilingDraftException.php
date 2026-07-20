<?php

namespace App\Exceptions\Tax;

use DomainException;

class LiquorTaxMonthlyFilingDraftException extends DomainException
{
    public static function alreadyConfirmed(int $year, int $month): self
    {
        return new self("Liquor tax monthly filing [{$year}-{$month}] is already confirmed.");
    }
}
