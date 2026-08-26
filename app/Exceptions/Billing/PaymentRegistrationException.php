<?php

namespace App\Exceptions\Billing;

use RuntimeException;

class PaymentRegistrationException extends RuntimeException
{
    public static function nonPositiveAmount(string $amount): self
    {
        return new self("入金額は正の値で入力してください: {$amount}");
    }

    public static function scheduleClosed(int $scheduleId): self
    {
        return new self("入金予定 {$scheduleId} は既に完了しています。");
    }

    public static function amountExceedsOutstanding(string $amount, string $outstanding): self
    {
        return new self("入金額 {$amount} が未回収額 {$outstanding} を超えています。");
    }
}
