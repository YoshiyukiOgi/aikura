<?php

namespace App\Exceptions\Tax;

use RuntimeException;

class LiquorTaxRuleResolutionException extends RuntimeException
{
    public static function categoryIsNotTaxable(string $categoryCode): self
    {
        return new self("Liquor tax category [{$categoryCode}] is not taxable.");
    }

    public static function missingRule(string $categoryCode, string $date, ?string $alcoholPercentage): self
    {
        $alcohol = $alcoholPercentage === null ? 'none' : $alcoholPercentage;

        return new self("No active liquor tax rule found for category [{$categoryCode}] on {$date}, alcohol percentage: {$alcohol}.");
    }
}
