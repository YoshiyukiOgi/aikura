<?php

namespace App\Exceptions\Pricing;

use DomainException;

class PriceResolutionException extends DomainException
{
    public static function notFound(int $customerId, int $productId, string $date): self
    {
        return new self("No active price was found for customer [{$customerId}], product [{$productId}] on [{$date}].");
    }
}

