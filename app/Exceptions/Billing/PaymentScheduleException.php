<?php

namespace App\Exceptions\Billing;

use DomainException;

class PaymentScheduleException extends DomainException
{
    public static function invoiceNotConfirmed(int $invoiceId, string $status): self
    {
        return new self("Invoice {$invoiceId} must be confirmed before payment schedule creation. Current status: {$status}.");
    }

    public static function alreadyExists(int $invoiceId): self
    {
        return new self("Payment schedule already exists for invoice {$invoiceId}.");
    }
}
