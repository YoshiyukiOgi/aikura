<?php

namespace App\Exceptions\Billing;

use RuntimeException;

class PaymentCancellationException extends RuntimeException
{
    public static function emptyReason(): self
    {
        return new self('Payment cancellation reason is required.');
    }

    public static function alreadyCancelled(int $paymentId): self
    {
        return new self("Payment {$paymentId} is already cancelled.");
    }
}
