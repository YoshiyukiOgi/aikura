<?php

namespace App\Exceptions\Billing;

use DomainException;

class PaymentScheduleException extends DomainException
{
    public static function invoiceNotConfirmed(int $invoiceId, string $status): self
    {
        return new self("請求書 {$invoiceId} は確定後でないと入金予定を作成できません。現在の状態: {$status}");
    }

    public static function alreadyExists(int $invoiceId): self
    {
        return new self("請求書 {$invoiceId} には既に入金予定があります。");
    }
}
