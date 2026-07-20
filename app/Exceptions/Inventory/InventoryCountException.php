<?php

namespace App\Exceptions\Inventory;

use DomainException;

class InventoryCountException extends DomainException
{
    public static function notDraft(): self { return new self('確定済みの棚卸は変更できません。'); }
    public static function incomplete(): self { return new self('実地数量が未入力の棚卸明細があります。'); }
    public static function missingVarianceReason(): self { return new self('差異がある棚卸明細には差異理由が必要です。'); }
    public static function alreadyClosed(): self { return new self('月次在庫が確定済みのため棚卸を作成できません。'); }
}
