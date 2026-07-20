<?php

namespace App\Exceptions\Tax;

use RuntimeException;

class TaxRateResolutionException extends RuntimeException
{
    public static function categoryDoesNotRequireRate(string $categoryCode): self
    {
        return new self("Consumption tax category [{$categoryCode}] does not require a tax rate.");
    }

    public static function missingRate(string $categoryCode, string $date): self
    {
        return new self("No active consumption tax rate found for category [{$categoryCode}] on {$date}.");
    }
}
