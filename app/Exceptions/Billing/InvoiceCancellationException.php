<?php

namespace App\Exceptions\Billing;

use RuntimeException;

class InvoiceCancellationException extends RuntimeException
{
    public static function emptyReason(): self
    {
        return new self('請求書取消理由が必要です。');
    }

    public static function notCancellable(int $invoiceId, string $status): self
    {
        return new self("請求書 {$invoiceId} は現在の状態 {$status} から取り消せません。");
    }
}
