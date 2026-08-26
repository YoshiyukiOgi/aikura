<?php

namespace App\Exceptions\Shipment;

use DomainException;

class ShipmentConfirmationException extends DomainException
{
    public static function notDraft(int $shipmentHeaderId, string $status): self
    {
        return new self("出荷伝票 [{$shipmentHeaderId}] は下書き状態でないと確定できません。現在の状態: {$status}");
    }

    public static function noLines(int $shipmentHeaderId): self
    {
        return new self("出荷伝票 [{$shipmentHeaderId}] に明細がありません。");
    }

    public static function lineHasNoDraftPrice(int $shipmentLineId): self
    {
        return new self("出荷明細 [{$shipmentLineId}] に下書き単価がありません。");
    }

    public static function instructionNotPicked(int $shipmentHeaderId): self
    {
        return new self("出荷伝票 [{$shipmentHeaderId}] はピッキング完了後に出荷確定できます。");
    }

    public static function lineLotAllocationIncomplete(int $shipmentLineId, string $lineQuantity, string $allocatedQuantity): self
    {
        return new self("出荷明細 [{$shipmentLineId}] の数量 {$lineQuantity} とロット割当数量 {$allocatedQuantity} が一致しません。");
    }
}
