<?php

namespace App\Exceptions\Operations;

use DomainException;

class OperationJobException extends DomainException
{
    public static function duplicateRunningJob(string $jobType, ?string $targetType, ?string $targetId): self
    {
        $target = trim(($targetType ?? 'none').' '.($targetId ?? 'none'));

        return new self("処理ジョブ [{$jobType}] は対象 [{$target}] で既に実行中です。");
    }

    public static function retryRequiresFailedJob(int $operationJobId, string $status): self
    {
        return new self("処理ジョブ [{$operationJobId}] は現在の状態 {$status} から再実行できません。");
    }

    public static function unsupportedReportType(string $reportType): self
    {
        return new self("未対応の帳票生成ジョブ種別です: {$reportType}");
    }

    public static function unsupportedAggregationType(string $aggregationType): self
    {
        return new self("未対応の月次集計ジョブ種別です: {$aggregationType}");
    }

    public static function unsupportedClosingType(string $closingType): self
    {
        return new self("未対応の月次締めジョブ種別です: {$closingType}");
    }
}
