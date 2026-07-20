<?php

namespace App\Exceptions\Billing;

use DomainException;

class ReceivableMonthlyBalanceReportExportException extends DomainException
{
    public static function noBalances(int $year, int $month): self
    {
        return new self("Receivable monthly balances [{$year}-{$month}] do not exist.");
    }

    public static function notConfirmed(int $year, int $month): self
    {
        return new self("Receivable monthly balances [{$year}-{$month}] are not confirmed.");
    }

    public static function unsupportedFormat(string $format): self
    {
        return new self("Unsupported receivable monthly balance report format [{$format}].");
    }

    public static function writeFailed(string $path): self
    {
        return new self("Failed to write receivable monthly balance report [{$path}].");
    }
}
