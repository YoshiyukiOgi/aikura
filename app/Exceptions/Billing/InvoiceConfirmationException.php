<?php

namespace App\Exceptions\Billing;

use DomainException;

class InvoiceConfirmationException extends DomainException
{
    public static function notDraft(int $invoiceId, string $status): self
    {
        return new self("Invoice [{$invoiceId}] must be draft to confirm, current status is [{$status}].");
    }

    public static function noLines(int $invoiceId): self
    {
        return new self("Invoice [{$invoiceId}] has no lines.");
    }
}

