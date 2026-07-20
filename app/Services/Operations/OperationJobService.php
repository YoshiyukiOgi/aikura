<?php

namespace App\Services\Operations;

use App\Exceptions\Operations\OperationJobException;
use App\Models\OperationJob;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Throwable;

class OperationJobService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @template TResult
     *
     * @param callable(OperationJob): TResult $callback
     * @return TResult
     */
    public function run(
        string $jobType,
        ?string $targetType,
        ?string $targetId,
        array $payload,
        ?string $reason,
        callable $callback,
        ?OperationJob $retryOf = null,
    ): mixed {
        $idempotencyKey = $this->idempotencyKey($jobType, $targetType, $targetId);
        $runningJob = null;

        $job = DB::transaction(function () use ($jobType, $targetType, $targetId, $payload, $reason, $retryOf, $idempotencyKey, &$runningJob): ?OperationJob {
            $runningJob = OperationJob::query()
                ->where('idempotency_key', $idempotencyKey)
                ->where('status', 'running')
                ->lockForUpdate()
                ->first();

            if ($runningJob !== null) {
                return null;
            }

            if ($retryOf !== null && $retryOf->status !== 'failed') {
                throw OperationJobException::retryRequiresFailedJob($retryOf->id, $retryOf->status);
            }

            $job = OperationJob::create([
                'job_type' => $jobType,
                'status' => 'running',
                'target_type' => $targetType,
                'target_id' => $targetId,
                'idempotency_key' => $idempotencyKey,
                'payload' => $payload,
                'attempts' => $retryOf === null ? 1 : $retryOf->attempts + 1,
                'retry_of_operation_job_id' => $retryOf?->id,
                'started_at' => now(),
                'reason' => $reason,
            ]);

            $this->auditLogService->record(new AuditLogData(
                event: $retryOf === null ? 'operation_job.started' : 'operation_job.retried',
                auditable: $job,
                afterValues: [
                    'job_type' => $job->job_type,
                    'target_type' => $job->target_type,
                    'target_id' => $job->target_id,
                    'attempts' => $job->attempts,
                    'retry_of_operation_job_id' => $job->retry_of_operation_job_id,
                ],
                reason: $reason,
            ));

            return $job;
        });

        if ($runningJob !== null) {
            $this->auditLogService->record(new AuditLogData(
                event: 'operation_job.duplicate_rejected',
                targetTable: 'operation_jobs',
                targetId: $runningJob->id,
                afterValues: [
                    'job_type' => $jobType,
                    'target_type' => $targetType,
                    'target_id' => $targetId,
                    'running_operation_job_id' => $runningJob->id,
                ],
                reason: $reason,
            ));

            throw OperationJobException::duplicateRunningJob($jobType, $targetType, $targetId);
        }

        try {
            $result = $callback($job);

            $job->forceFill([
                'status' => 'completed',
                'finished_at' => now(),
            ])->save();

            $this->auditLogService->record(new AuditLogData(
                event: 'operation_job.completed',
                auditable: $job,
                afterValues: [
                    'job_type' => $job->job_type,
                    'target_type' => $job->target_type,
                    'target_id' => $job->target_id,
                    'attempts' => $job->attempts,
                ],
                reason: $reason,
            ));

            return $result;
        } catch (Throwable $exception) {
            $job->forceFill([
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => $exception->getMessage(),
            ])->save();

            $this->auditLogService->record(new AuditLogData(
                event: 'operation_job.failed',
                auditable: $job,
                afterValues: [
                    'job_type' => $job->job_type,
                    'target_type' => $job->target_type,
                    'target_id' => $job->target_id,
                    'attempts' => $job->attempts,
                    'error_message' => $job->error_message,
                ],
                reason: $reason,
            ));

            throw $exception;
        }
    }

    public function retry(OperationJob $failedJob, callable $callback): mixed
    {
        return $this->run(
            jobType: $failedJob->job_type,
            targetType: $failedJob->target_type,
            targetId: $failedJob->target_id,
            payload: $failedJob->payload ?? [],
            reason: $failedJob->reason,
            callback: $callback,
            retryOf: $failedJob,
        );
    }

    public function idempotencyKey(string $jobType, ?string $targetType, ?string $targetId): string
    {
        return hash('sha256', implode('|', [
            $jobType,
            $targetType ?? '',
            $targetId ?? '',
        ]));
    }
}
