<?php

namespace App\Exceptions\Tax;

use DomainException;

class LiquorTaxMonthlyFilingReopenException extends DomainException
{
    public static function emptyReason(): self
    {
        return new self('確定を取り消す理由を入力してください。');
    }

    public static function nonConfirmedFiling(int $year, int $month): self
    {
        return new self("{$year}年{$month}月分は確定済みではないため、下書きに戻せません。");
    }

    public static function notLatestFinalizedFiling(int $year, int $month): self
    {
        return new self("{$year}年{$month}月分より新しい確定済み申告があるため、下書きに戻せません。");
    }
}
