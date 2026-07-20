<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    public function record(AuditLogData $data): AuditLog
    {
        $auditable = $data->auditable;

        return AuditLog::create([
            'occurred_at' => Carbon::now(),
            'user_id' => $data->user?->id ?? $this->currentUserId(),
            'approved_by_user_id' => $data->approver?->id,
            'event' => $data->event,
            'auditable_type' => $auditable ? $auditable::class : null,
            'auditable_id' => $auditable?->getKey(),
            'target_table' => $data->targetTable ?? $this->tableName($auditable),
            'target_id' => $data->targetId ?? $auditable?->getKey(),
            'before_values' => $data->beforeValues,
            'after_values' => $data->afterValues,
            'reason' => $data->reason,
            'ip_address' => $data->ipAddress ?? $this->request()?->ip(),
            'user_agent' => $data->userAgent ?? $this->request()?->userAgent(),
            'request_id' => $data->requestId ?? $this->requestId(),
        ]);
    }

    public function recordModelChange(
        string $event,
        Model $model,
        ?array $beforeValues = null,
        ?array $afterValues = null,
        ?string $reason = null,
    ): AuditLog {
        return $this->record(new AuditLogData(
            event: $event,
            auditable: $model,
            beforeValues: $beforeValues,
            afterValues: $afterValues,
            reason: $reason,
        ));
    }

    private function currentUserId(): ?int
    {
        $user = Auth::user();

        if (! $user instanceof Authenticatable || ! method_exists($user, 'getAuthIdentifier')) {
            return null;
        }

        $identifier = $user->getAuthIdentifier();

        return is_numeric($identifier) ? (int) $identifier : null;
    }

    private function tableName(?Model $model): ?string
    {
        return $model?->getTable();
    }

    private function request(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = request();

        return $request instanceof Request ? $request : null;
    }

    private function requestId(): ?string
    {
        return $this->request()?->attributes->get('request_id');
    }
}

