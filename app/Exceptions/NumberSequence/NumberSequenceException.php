<?php

namespace App\Exceptions\NumberSequence;

use DomainException;

class NumberSequenceException extends DomainException
{
    public static function notFound(string $code): self
    {
        return new self("Number sequence [{$code}] was not found.");
    }

    public static function inactive(string $code): self
    {
        return new self("Number sequence [{$code}] is inactive.");
    }

    public static function unsupportedResetType(string $resetType): self
    {
        return new self("Number sequence reset type [{$resetType}] is not supported.");
    }
}

