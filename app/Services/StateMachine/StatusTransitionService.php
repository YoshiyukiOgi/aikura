<?php

namespace App\Services\StateMachine;

use App\Exceptions\StateMachine\InvalidStatusTransitionException;
use App\Models\User;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StatusTransitionService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function canTransition(string $machine, string $from, string $to): bool
    {
        $transitions = $this->transitionsFor($machine);

        return in_array($to, $transitions[$from] ?? [], true);
    }

    public function transition(
        Model $model,
        string $machine,
        string $to,
        ?string $reason = null,
        ?User $user = null,
        ?User $approver = null,
        string $statusColumn = 'status',
        bool $audit = true,
    ): StatusTransitionResult {
        return DB::transaction(function () use ($model, $machine, $to, $reason, $user, $approver, $statusColumn, $audit): StatusTransitionResult {
            $from = $this->currentStatus($model, $statusColumn);

            if (! $this->canTransition($machine, $from, $to)) {
                throw InvalidStatusTransitionException::forTransition($machine, $from, $to);
            }

            $model->setAttribute($statusColumn, $to);
            $model->save();

            $auditLog = null;

            if ($audit) {
                $auditLog = $this->auditLogService->record(new AuditLogData(
                    event: "{$machine}.status_changed",
                    auditable: $model,
                    beforeValues: [$statusColumn => $from],
                    afterValues: [$statusColumn => $to],
                    reason: $reason,
                    user: $user,
                    approver: $approver,
                ));
            }

            return new StatusTransitionResult(
                model: $model,
                from: $from,
                to: $to,
                auditLog: $auditLog,
            );
        });
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function transitionsFor(string $machine): array
    {
        $transitions = config("state_machines.{$machine}");

        if (! is_array($transitions)) {
            throw InvalidStatusTransitionException::forUnknownMachine($machine);
        }

        return $transitions;
    }

    private function currentStatus(Model $model, string $statusColumn): string
    {
        $status = $model->getAttribute($statusColumn);

        if (! is_string($status) || $status === '') {
            throw InvalidStatusTransitionException::forMissingStatus($model::class);
        }

        return $status;
    }
}

