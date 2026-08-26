<?php

namespace App\Exceptions\Inventory;

use DomainException;

class InventoryReportExportException extends DomainException
{
    public static function noStockBalances(): self
    {
        return new self('在庫帳票の対象となる在庫残高がありません。');
    }

    public static function noLotStockBalances(): self
    {
        return new self('在庫帳票の対象となるロット在庫残高がありません。');
    }

    public static function unsupportedFormat(string $format): self
    {
        return new self("未対応の在庫帳票形式です: {$format}");
    }

    public static function writeFailed(string $path): self
    {
        return new self("在庫帳票を書き出せませんでした: {$path}");
    }
}
