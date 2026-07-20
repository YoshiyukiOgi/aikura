<?php

namespace App\Exceptions\Authorization;

use DomainException;

class PermissionDeniedException extends DomainException
{
    public static function forPermission(string $permissionCode): self
    {
        return new self("Permission [{$permissionCode}] is required.");
    }

    public static function forInactiveUser(): self
    {
        return new self('The user is inactive.');
    }
}

