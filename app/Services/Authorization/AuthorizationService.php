<?php

namespace App\Services\Authorization;

use App\Exceptions\Authorization\PermissionDeniedException;
use App\Models\User;

class AuthorizationService
{
    public function can(User $user, string $permissionCode): bool
    {
        return $user->is_active && $user->hasPermission($permissionCode);
    }

    public function assertCan(User $user, string $permissionCode): void
    {
        if (! $user->is_active) {
            throw PermissionDeniedException::forInactiveUser();
        }

        if (! $user->hasPermission($permissionCode)) {
            throw PermissionDeniedException::forPermission($permissionCode);
        }
    }
}

