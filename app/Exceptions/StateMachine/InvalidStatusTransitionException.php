<?php

namespace App\Exceptions\StateMachine;

use DomainException;

class InvalidStatusTransitionException extends DomainException
{
    public static function forTransition(string $machine, string $from, string $to): self
    {
        return new self("状態遷移 [{$machine}: {$from} -> {$to}] は許可されていません。");
    }

    public static function forUnknownMachine(string $machine): self
    {
        return new self("状態管理 [{$machine}] が定義されていません。");
    }

    public static function forMissingStatus(string $modelClass): self
    {
        return new self("モデル [{$modelClass}] に状態値がありません。");
    }
}

