<?php

namespace App\Exceptions\Billing;

use RuntimeException;

class PaymentCancellationException extends RuntimeException
{
    public static function emptyReason(): self
    {
        return new self('入金取消理由が必要です。');
    }

    public static function alreadyCancelled(int $paymentId): self
    {
        return new self("入金 {$paymentId} は既に取消済みです。");
    }
}
