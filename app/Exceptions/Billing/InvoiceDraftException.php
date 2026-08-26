<?php

namespace App\Exceptions\Billing;

use DomainException;

class InvoiceDraftException extends DomainException
{
    public static function noBillableShipments(): self
    {
        return new self('請求対象の出荷が見つかりません。');
    }

    public static function shipmentCustomerMismatch(int $shipmentId): self
    {
        return new self("出荷伝票 [{$shipmentId}] は別の取引先に属しています。");
    }

    public static function shipmentNotBillable(int $shipmentId): self
    {
        return new self("出荷伝票 [{$shipmentId}] は請求対象外です。");
    }

    public static function shipmentLineMissingSnapshot(int $shipmentLineId): self
    {
        return new self("出荷明細 [{$shipmentLineId}] に確定スナップショットがありません。");
    }
}

