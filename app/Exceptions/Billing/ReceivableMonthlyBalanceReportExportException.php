<?php

namespace App\Exceptions\Billing;

use DomainException;

class ReceivableMonthlyBalanceReportExportException extends DomainException
{
    public static function noBalances(int $year, int $month): self
    {
        return new self("{$year}年{$month}月の売掛月次残高がありません。");
    }

    public static function notConfirmed(int $year, int $month): self
    {
        return new self("{$year}年{$month}月の売掛月次残高は未確定です。");
    }

    public static function unsupportedFormat(string $format): self
    {
        return new self("未対応の売掛月次帳票形式です: {$format}");
    }

    public static function writeFailed(string $path): self
    {
        return new self("売掛月次帳票を書き出せませんでした: {$path}");
    }
}
