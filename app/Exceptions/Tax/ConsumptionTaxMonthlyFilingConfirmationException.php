<?php

namespace App\Exceptions\Tax;

use DomainException;

class ConsumptionTaxMonthlyFilingConfirmationException extends DomainException
{
    public static function emptyReason(): self
    {
        return new self('消費税月次申告の確定理由が必要です。');
    }

    public static function noDraftFiling(int $year, int $month): self
    {
        return new self("{$year}年{$month}月の下書き消費税月次申告がありません。");
    }

    public static function nonDraftFiling(int $year, int $month): self
    {
        return new self("{$year}年{$month}月の消費税月次申告は下書き状態ではありません。");
    }
}
