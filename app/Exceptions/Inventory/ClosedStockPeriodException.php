<?php

namespace App\Exceptions\Inventory;

use DomainException;

class ClosedStockPeriodException extends DomainException
{
    public static function forDate(string $movementDate): self
    {
        return new self("Stock period containing [{$movementDate}] is already confirmed or closed.");
    }
}
