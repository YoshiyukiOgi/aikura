<?php

namespace App\Exceptions\Authorization;

use DomainException;

class PermissionDeniedException extends DomainException
{
    public static function forPermission(string $permissionCode): self
    {
        return new self("権限 [{$permissionCode}] が必要です。");
    }

    public static function forInactiveUser(): self
    {
        return new self('ユーザーが無効です。');
    }
}

