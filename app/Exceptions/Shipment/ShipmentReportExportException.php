<?php

namespace App\Exceptions\Shipment;

use DomainException;

class ShipmentReportExportException extends DomainException
{
    public static function notConfirmed(int $shipmentHeaderId, string $status): self
    {
        return new self("出荷伝票 [{$shipmentHeaderId}] は確定済みでないと帳票出力できません。現在の状態: {$status}");
    }

    public static function unsupportedFormat(string $format): self
    {
        return new self("未対応の出荷帳票形式です: {$format}");
    }

    public static function writeFailed(string $path): self
    {
        return new self("出荷帳票を書き出せませんでした: {$path}");
    }
}
