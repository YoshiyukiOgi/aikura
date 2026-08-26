<?php

namespace App\Exceptions\SalesOrder;

use DomainException;

class SalesOrderException extends DomainException
{
    public static function emptyLines(): self
    {
        return new self('受注には明細が1件以上必要です。');
    }

    public static function inactiveCustomer(int $customerId): self
    {
        return new self("取引先 [{$customerId}] は無効です。");
    }

    public static function inactiveProduct(int $productId): self
    {
        return new self("商品 [{$productId}] は無効、または販売対象外です。");
    }

    public static function inactiveUnit(int $unitId): self
    {
        return new self("単位 [{$unitId}] は無効です。");
    }

    public static function invalidQuantity(string $quantity): self
    {
        return new self("受注明細数量は0より大きい必要があります: {$quantity}");
    }

    public static function invalidUnitPrice(string $unitPrice): self
    {
        return new self("受注明細単価は0より大きい必要があります: {$unitPrice}");
    }

    public static function notPriceEditable(int $salesOrderId, string $status): self
    {
        return new self("受注 [{$salesOrderId}] は現在の状態 {$status} では採番・単価確定できません。");
    }

    public static function notEditable(int $salesOrderId, string $status): self
    {
        return new self("受注 [{$salesOrderId}] は現在の状態 {$status} では編集できません。");
    }

    public static function retailManagedOrderCannotBeChanged(int $salesOrderId): self
    {
        return new self("受注 [{$salesOrderId}] は小売側管理のため、酒蔵側では変更できません。");
    }

    public static function lineDoesNotBelong(int $lineId, int $salesOrderId): self
    {
        return new self("受注明細 [{$lineId}] は受注 [{$salesOrderId}] に属していません。");
    }

    public static function instructedLineCannotChange(int $lineId): self
    {
        return new self("受注明細 [{$lineId}] には既に出荷指示数量があるため、商品と単位を変更できません。");
    }

    public static function quantityBelowInstructed(int $lineId, string $instructedQuantity): self
    {
        return new self("受注明細 [{$lineId}] は出荷指示数量 {$instructedQuantity} 未満に減らせません。");
    }

    public static function instructedLineCannotDelete(int $lineId): self
    {
        return new self("受注明細 [{$lineId}] には既に出荷指示数量があるため削除できません。");
    }

    public static function emptyCancellationReason(): self
    {
        return new self('受注取消理由が必要です。');
    }

    public static function alreadyCancelled(int $salesOrderId): self
    {
        return new self("受注 [{$salesOrderId}] は既に取消済みです。");
    }

    public static function alreadyInstructed(int $salesOrderId): self
    {
        return new self("受注 [{$salesOrderId}] には出荷指示数量があるため、直接取消できません。");
    }
}
