<?php

namespace App\Exceptions\Billing;

use DomainException;

class InvoiceConfirmationException extends DomainException
{
    public static function notDraft(int $invoiceId, string $status): self
    {
        return new self("請求書 [{$invoiceId}] は下書き状態でないと確定できません。現在の状態: {$status}");
    }

    public static function noLines(int $invoiceId): self
    {
        return new self("請求書 [{$invoiceId}] に明細がありません。");
    }
}

