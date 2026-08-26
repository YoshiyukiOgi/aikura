<?php

namespace App\Exceptions\Shipment;

use DomainException;

class ShipmentPickException extends DomainException
{
    public static function lotAllocationRequired(int $lineId): self
    {
        return new self("出荷指示明細 {$lineId} は、ピッキング前にロット割当が必要です。");
    }

    public static function lotAllocationIncomplete(int $lineId, string $required, string $allocated): self
    {
        return new self("出荷指示明細 {$lineId} のロット割当が不足しています。必要数: {$required} / 割当済み: {$allocated}");
    }

    public static function lotAlcoholAnalysisRequired(int $lotId): self
    {
        return new self("ロット {$lotId} はアルコール分析確定が必要です。");
    }

    public static function lotApprovalRequired(int $lotId): self
    {
        return new self("ロット {$lotId} はアルコール許容範囲外のため、管理者承認が必要です。");
    }

    public static function emptyLines(): self
    {
        return new self('ピッキングには明細が1件以上必要です。');
    }

    public static function invalidQuantity(string $quantity): self
    {
        return new self("ピッキング数量は0より大きい必要があります: {$quantity}");
    }

    public static function cancelledInstruction(int $shipmentInstructionId): self
    {
        return new self("出荷指示 [{$shipmentInstructionId}] は取消済みです。");
    }

    public static function lineDoesNotBelongToInstruction(int $lineId, int $shipmentInstructionId): self
    {
        return new self("出荷指示明細 [{$lineId}] は出荷指示 [{$shipmentInstructionId}] に属していません。");
    }

    public static function duplicateInstructionLine(int $lineId): self
    {
        return new self("出荷指示明細 [{$lineId}] が同じピッキング内で重複しています。");
    }

    public static function exceedsRemainingQuantity(int $lineId, string $remainingQuantity, string $requestedQuantity): self
    {
        return new self("出荷指示明細 [{$lineId}] の残ピッキング数 {$remainingQuantity} が、要求数 {$requestedQuantity} を下回っています。");
    }

    public static function emptyCancellationReason(): self
    {
        return new self('ピッキング取消理由が必要です。');
    }

    public static function alreadyCancelled(int $shipmentPickId): self
    {
        return new self("ピッキング [{$shipmentPickId}] は既に取消済みです。");
    }

    public static function alreadyConvertedToShipment(int $shipmentPickId): self
    {
        return new self('このピッキングから出荷伝票が作成済みです。先に出荷画面で下書きの出荷伝票を取り消してください。');
    }
}
