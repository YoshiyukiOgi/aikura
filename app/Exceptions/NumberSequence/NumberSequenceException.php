<?php

namespace App\Exceptions\NumberSequence;

use DomainException;

class NumberSequenceException extends DomainException
{
    public static function notFound(string $code): self
    {
        return new self("採番設定 [{$code}] が見つかりません。");
    }

    public static function inactive(string $code): self
    {
        return new self("採番設定 [{$code}] は無効です。");
    }

    public static function unsupportedResetType(string $resetType): self
    {
        return new self("採番リセット種別 [{$resetType}] には対応していません。");
    }
}

