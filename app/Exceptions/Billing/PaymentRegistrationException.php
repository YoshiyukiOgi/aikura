<?php

namespace App\Exceptions\Billing;

use RuntimeException;

class PaymentRegistrationException extends RuntimeException
{
    public static function nonPositiveAmount(string $amount): self
    {
        return new self("Payment amount must be positive: {$amount}.");
    }

    public static function scheduleClosed(int $scheduleId): self
    {
        return new self("Payment schedule {$scheduleId} is already closed.");
    }

    public static function amountExceedsOutstanding(string $amount, string $outstanding): self
    {
        return new self("Payment amount {$amount} exceeds outstanding amount {$outstanding}.");
    }
}
