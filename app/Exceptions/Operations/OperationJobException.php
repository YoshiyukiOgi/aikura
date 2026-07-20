<?php

namespace App\Exceptions\Operations;

use DomainException;

class OperationJobException extends DomainException
{
    public static function duplicateRunningJob(string $jobType, ?string $targetType, ?string $targetId): self
    {
        $target = trim(($targetType ?? 'none').' '.($targetId ?? 'none'));

        return new self("Operation job [{$jobType}] for [{$target}] is already running.");
    }

    public static function retryRequiresFailedJob(int $operationJobId, string $status): self
    {
        return new self("Operation job [{$operationJobId}] cannot be retried from status [{$status}].");
    }

    public static function unsupportedReportType(string $reportType): self
    {
        return new self("Unsupported report generation job type [{$reportType}].");
    }

    public static function unsupportedAggregationType(string $aggregationType): self
    {
        return new self("Unsupported monthly aggregation job type [{$aggregationType}].");
    }

    public static function unsupportedClosingType(string $closingType): self
    {
        return new self("Unsupported monthly closing job type [{$closingType}].");
    }
}
