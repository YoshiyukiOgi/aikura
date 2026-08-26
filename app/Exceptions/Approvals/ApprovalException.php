<?php

namespace App\Exceptions\Approvals;

use DomainException;

class ApprovalException extends DomainException
{
    public static function invalidStatus(int $approvalRequestId, string $status, string $action): self
    {
        return new self("承認依頼 [{$approvalRequestId}] は現在の状態 {$status} のため {$action} できません。");
    }

    public static function selfApprovalNotAllowed(int $approvalRequestId): self
    {
        return new self("承認依頼 [{$approvalRequestId}] は申請者自身では承認できません。");
    }

    public static function approvedRequestRequired(string $actionType, string $targetType, string $targetId): self
    {
        return new self("[{$targetType}:{$targetId}] に対する {$actionType} には承認済み依頼が必要です。");
    }

    public static function approvalTargetMismatch(int $approvalRequestId): self
    {
        return new self("承認依頼 [{$approvalRequestId}] の対象が、要求された操作対象と一致しません。");
    }
}
