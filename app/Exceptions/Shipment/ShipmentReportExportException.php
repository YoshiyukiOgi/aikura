<?php

namespace App\Exceptions\Shipment;

use DomainException;

class ShipmentReportExportException extends DomainException
{
    public static function notConfirmed(int $shipmentHeaderId, string $status): self
    {
        return new self("Shipment [{$shipmentHeaderId}] must be confirmed to generate report, current status is [{$status}].");
    }

    public static function unsupportedFormat(string $format): self
    {
        return new self("Shipment report format [{$format}] is not supported.");
    }

    public static function writeFailed(string $path): self
    {
        return new self("Shipment report file [{$path}] could not be written.");
    }
}
