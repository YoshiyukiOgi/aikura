<?php

namespace App\Services\Audit;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogData
{
    public function __construct(
        public readonly string $event,
        public readonly ?Model $auditable = null,
        public readonly ?string $targetTable = null,
        public readonly string|int|null $targetId = null,
        public readonly ?array $beforeValues = null,
        public readonly ?array $afterValues = null,
        public readonly ?string $reason = null,
        public readonly ?User $user = null,
        public readonly ?User $approver = null,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
        public readonly ?string $requestId = null,
    ) {
    }
}

