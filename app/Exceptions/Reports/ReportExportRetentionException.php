<?php

namespace App\Exceptions\Reports;

use DomainException;

class ReportExportRetentionException extends DomainException
{
    public static function unsupportedDisk(string $disk): self
    {
        return new self("Unsupported report export disk [{$disk}].");
    }

    public static function missingFile(string $path): self
    {
        return new self("Report export file is missing [{$path}].");
    }

    public static function fileSizeMismatch(string $path): self
    {
        return new self("Report export file size does not match history [{$path}].");
    }

    public static function checksumMismatch(string $path): self
    {
        return new self("Report export checksum does not match history [{$path}].");
    }
}
