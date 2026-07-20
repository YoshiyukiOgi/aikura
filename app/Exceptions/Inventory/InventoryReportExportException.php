<?php

namespace App\Exceptions\Inventory;

use DomainException;

class InventoryReportExportException extends DomainException
{
    public static function noStockBalances(): self
    {
        return new self('No stock balances exist for inventory report.');
    }

    public static function noLotStockBalances(): self
    {
        return new self('No lot stock balances exist for inventory report.');
    }

    public static function unsupportedFormat(string $format): self
    {
        return new self("Unsupported inventory report format [{$format}].");
    }

    public static function writeFailed(string $path): self
    {
        return new self("Failed to write inventory report [{$path}].");
    }
}
