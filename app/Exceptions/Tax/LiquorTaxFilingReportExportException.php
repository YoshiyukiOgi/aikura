<?php

namespace App\Exceptions\Tax;

use RuntimeException;

class LiquorTaxFilingReportExportException extends RuntimeException
{
    public static function notConfirmed(int $filingId, string $status): self
    {
        return new self("Liquor tax monthly filing {$filingId} must be confirmed before report export. Current status: {$status}.");
    }

    public static function unsupportedFormat(string $format): self
    {
        return new self("Unsupported liquor tax filing report format: {$format}.");
    }

    public static function writeFailed(string $path): self
    {
        return new self("Failed to write liquor tax filing report file: {$path}.");
    }
}
