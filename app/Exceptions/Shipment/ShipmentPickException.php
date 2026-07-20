<?php

namespace App\Exceptions\Shipment;

use DomainException;

class ShipmentPickException extends DomainException
{
    public static function lotAllocationRequired(int $lineId): self
    {
        return new self("Shipment instruction line {$lineId} requires lot allocation before picking.");
    }

    public static function lotAllocationIncomplete(int $lineId, string $required, string $allocated): self
    {
        return new self("Shipment instruction line {$lineId} lot allocation is incomplete. Required: {$required}; allocated: {$allocated}.");
    }

    public static function lotAlcoholAnalysisRequired(int $lotId): self
    {
        return new self("Production lot {$lotId} requires a confirmed alcohol analysis.");
    }

    public static function lotApprovalRequired(int $lotId): self
    {
        return new self("Production lot {$lotId} is outside the alcohol range and requires administrator approval.");
    }

    public static function emptyLines(): self
    {
        return new self('Shipment pick requires at least one line.');
    }

    public static function invalidQuantity(string $quantity): self
    {
        return new self("Shipment pick line quantity [{$quantity}] must be greater than zero.");
    }

    public static function cancelledInstruction(int $shipmentInstructionId): self
    {
        return new self("Shipment instruction [{$shipmentInstructionId}] is cancelled.");
    }

    public static function lineDoesNotBelongToInstruction(int $lineId, int $shipmentInstructionId): self
    {
        return new self("Shipment instruction line [{$lineId}] does not belong to instruction [{$shipmentInstructionId}].");
    }

    public static function duplicateInstructionLine(int $lineId): self
    {
        return new self("Shipment instruction line [{$lineId}] is duplicated in the same shipment pick.");
    }

    public static function exceedsRemainingQuantity(int $lineId, string $remainingQuantity, string $requestedQuantity): self
    {
        return new self("Shipment instruction line [{$lineId}] remaining pick quantity [{$remainingQuantity}] is less than requested pick quantity [{$requestedQuantity}].");
    }

    public static function emptyCancellationReason(): self
    {
        return new self('Shipment pick cancellation reason is required.');
    }

    public static function alreadyCancelled(int $shipmentPickId): self
    {
        return new self("Shipment pick [{$shipmentPickId}] is already cancelled.");
    }

    public static function alreadyConvertedToShipment(int $shipmentPickId): self
    {
        return new self('このピッキングから出荷伝票が作成済みです。先に出荷画面で下書きの出荷伝票を取り消してください。');
    }
}
