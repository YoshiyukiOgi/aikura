<?php

namespace App\Exceptions\Billing;

use RuntimeException;

class InvoiceCancellationException extends RuntimeException
{
    public static function emptyReason(): self
    {
        return new self('Invoice cancellation reason is required.');
    }

    public static function notCancellable(int $invoiceId, string $status): self
    {
        return new self("Invoice {$invoiceId} cannot be cancelled from status: {$status}.");
    }
}
