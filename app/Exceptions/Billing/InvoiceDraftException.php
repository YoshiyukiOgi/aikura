<?php

namespace App\Exceptions\Billing;

use DomainException;

class InvoiceDraftException extends DomainException
{
    public static function noBillableShipments(): self
    {
        return new self('No billable shipments were found.');
    }

    public static function shipmentCustomerMismatch(int $shipmentId): self
    {
        return new self("Shipment [{$shipmentId}] belongs to a different customer.");
    }

    public static function shipmentNotBillable(int $shipmentId): self
    {
        return new self("Shipment [{$shipmentId}] is not billable.");
    }

    public static function shipmentLineMissingSnapshot(int $shipmentLineId): self
    {
        return new self("Shipment line [{$shipmentLineId}] does not have a confirmed snapshot.");
    }
}

