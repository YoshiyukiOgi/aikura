<?php

namespace App\Exceptions\Approvals;

use DomainException;

class ApprovalException extends DomainException
{
    public static function invalidStatus(int $approvalRequestId, string $status, string $action): self
    {
        return new self("Approval request [{$approvalRequestId}] with status [{$status}] cannot be {$action}.");
    }

    public static function selfApprovalNotAllowed(int $approvalRequestId): self
    {
        return new self("Approval request [{$approvalRequestId}] cannot be approved by requester.");
    }

    public static function approvedRequestRequired(string $actionType, string $targetType, string $targetId): self
    {
        return new self("Approved request is required for [{$actionType}] on [{$targetType}:{$targetId}].");
    }

    public static function approvalTargetMismatch(int $approvalRequestId): self
    {
        return new self("Approval request [{$approvalRequestId}] does not match the requested action target.");
    }
}
