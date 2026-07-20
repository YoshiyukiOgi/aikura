<?php

namespace App\Exceptions\Billing;

use RuntimeException;

class InvoiceReportExportException extends RuntimeException
{
    public static function notConfirmed(int $invoiceId, string $status): self
    {
        return new self("Invoice {$invoiceId} must be confirmed before report export. Current status: {$status}.");
    }

    public static function unsupportedFormat(string $format): self
    {
        return new self("Unsupported invoice report format: {$format}.");
    }

    public static function writeFailed(string $path): self
    {
        return new self("Failed to write invoice report file: {$path}.");
    }
}
