<?php

namespace App\Exceptions\Billing;

use RuntimeException;

class SalesReturnException extends RuntimeException
{
    public static function emptyReason(): self
    {
        return new self('返品理由が必要です。');
    }

    public static function emptyLines(): self
    {
        return new self('返品には明細が1件以上必要です。');
    }

    public static function sourceInvoiceNotConfirmed(int $invoiceId, string $status): self
    {
        return new self("元請求書 [{$invoiceId}] は確定済みである必要があります。現在の状態: {$status}");
    }

    public static function customerMismatch(int $customerId, int $invoiceCustomerId): self
    {
        return new self("返品取引先 [{$customerId}] が元請求書の取引先 [{$invoiceCustomerId}] と一致しません。");
    }

    public static function quantityMustBePositive(): self
    {
        return new self('返品数量は正の値で入力してください。');
    }

    public static function quantityExceedsRemaining(string $requested, string $remaining): self
    {
        return new self("返品数量 {$requested} が返品可能残数 {$remaining} を超えています。");
    }

    public static function stockLocationRequired(string $stockAction): self
    {
        return new self("返品在庫処理 [{$stockAction}] には在庫場所の指定が必要です。");
    }

    public static function invalidStockAction(string $stockAction): self
    {
        return new self("返品在庫処理 [{$stockAction}] には対応していません。");
    }

    public static function productionLotRequiredForReturnStock(): self
    {
        return new self('通常販売在庫へ戻す返品ではロット割当が必要です。');
    }

    public static function productionLotNotInSourceShipment(int $productionLotId): self
    {
        return new self("ロット [{$productionLotId}] は元出荷明細で使用されていません。");
    }

    public static function lotQuantityMismatch(string $returnQuantity, string $lotQuantity): self
    {
        return new self("返品ロット数量合計 {$lotQuantity} は返品数量 {$returnQuantity} と一致する必要があります。");
    }

    public static function lotQuantityExceedsSource(int $productionLotId, string $requested, string $sourceQuantity): self
    {
        return new self("ロット [{$productionLotId}] の返品数量 {$requested} が元出荷ロット数量 {$sourceQuantity} を超えています。");
    }

    public static function emptyCancelReason(): self
    {
        return new self('返品取消理由が必要です。');
    }

    public static function alreadyCancelled(int $salesReturnId): self
    {
        return new self("返品 [{$salesReturnId}] は既に取消済みです。");
    }
}
