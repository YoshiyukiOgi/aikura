<?php

namespace App\Services\StateMachine;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class StatusTransitionResult
{
    public function __construct(
        public readonly Model $model,
        public readonly string $from,
        public readonly string $to,
        public readonly ?AuditLog $auditLog,
    ) {
    }
}

