<?php

namespace App\Services\Approvals;

use App\Exceptions\Approvals\ApprovalException;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

class ApprovalService
{
    public const ACTION_DOCUMENT_CANCEL = 'document_cancel';

    public const ACTION_CLOSING_REOPEN = 'closing_reopen';

    public const ACTION_PRICE_CHANGE = 'price_change';

    public const ACTION_TAX_CONFIRM = 'tax_confirm';

    public const ACTION_ROLE_PERMISSION_CHANGE = 'role_permission_change';

    public const ACTION_ALCOHOL_LOT_EXCEPTION = 'alcohol_lot_exception';

    public const ACTION_LIQUOR_TAX_ADJUSTMENT = 'liquor_tax_adjustment';

    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function request(
        User $requester,
        string $actionType,
        string $targetType,
        string $targetId,
        string $reason,
        array $payload = [],
    ): ApprovalRequest {
        return DB::transaction(function () use ($requester, $actionType, $targetType, $targetId, $reason, $payload): ApprovalRequest {
            $approvalRequest = ApprovalRequest::create([
                'approval_number' => $this->nextNumber(),
                'status' => 'pending',
                'action_type' => $actionType,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'requested_by_user_id' => $requester->id,
                'requested_at' => now(),
                'payload' => $payload,
                'reason' => $reason,
            ]);

            $this->recordAction($approvalRequest, 'requested', null, 'pending', $requester, $reason);
            $this->audit('approval.requested', $approvalRequest, $requester, $reason);

            return $approvalRequest->load(['requester', 'approver', 'actions.user']);
        });
    }

    public function approve(ApprovalRequest $approvalRequest, User $approver, ?string $comment = null): ApprovalRequest
    {
        return DB::transaction(function () use ($approvalRequest, $approver, $comment): ApprovalRequest {
            $approvalRequest->refresh();
            if ($approvalRequest->status !== 'pending') {
                throw ApprovalException::invalidStatus($approvalRequest->id, $approvalRequest->status, 'approved');
            }

            if ($approvalRequest->requested_by_user_id === $approver->id) {
                throw ApprovalException::selfApprovalNotAllowed($approvalRequest->id);
            }

            $from = $approvalRequest->status;
            $approvalRequest->update([
                'status' => 'approved',
                'approved_by_user_id' => $approver->id,
                'approved_at' => now(),
                'approver_comment' => $comment,
            ]);

            $this->recordAction($approvalRequest, 'approved', $from, 'approved', $approver, $comment);
            $this->audit('approval.approved', $approvalRequest, $approver, $comment);

            return $approvalRequest->load(['requester', 'approver', 'actions.user']);
        });
    }

    public function reject(ApprovalRequest $approvalRequest, User $approver, string $comment): ApprovalRequest
    {
        return $this->finishAs($approvalRequest, $approver, 'rejected', $comment);
    }

    public function returnForCorrection(ApprovalRequest $approvalRequest, User $approver, string $comment): ApprovalRequest
    {
        return $this->finishAs($approvalRequest, $approver, 'returned', $comment);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function resubmit(ApprovalRequest $approvalRequest, User $requester, string $reason, array $payload = []): ApprovalRequest
    {
        return DB::transaction(function () use ($approvalRequest, $requester, $reason, $payload): ApprovalRequest {
            $approvalRequest->refresh();
            if ($approvalRequest->status !== 'returned') {
                throw ApprovalException::invalidStatus($approvalRequest->id, $approvalRequest->status, 'resubmitted');
            }

            $from = $approvalRequest->status;
            $approvalRequest->update([
                'status' => 'pending',
                'requested_by_user_id' => $requester->id,
                'approved_by_user_id' => null,
                'requested_at' => now(),
                'approved_at' => null,
                'rejected_at' => null,
                'returned_at' => null,
                'payload' => $payload === [] ? $approvalRequest->payload : $payload,
                'reason' => $reason,
                'return_reason' => null,
                'approver_comment' => null,
            ]);

            $this->recordAction($approvalRequest, 'resubmitted', $from, 'pending', $requester, $reason);
            $this->audit('approval.resubmitted', $approvalRequest, $requester, $reason);

            return $approvalRequest->load(['requester', 'approver', 'actions.user']);
        });
    }

    public function assertApprovedFor(int $approvalRequestId, string $actionType, string $targetType, string $targetId): ApprovalRequest
    {
        $approvalRequest = ApprovalRequest::find($approvalRequestId);

        if (! $approvalRequest || $approvalRequest->status !== 'approved' || $approvalRequest->consumed_at !== null) {
            throw ApprovalException::approvedRequestRequired($actionType, $targetType, $targetId);
        }

        if ($approvalRequest->action_type !== $actionType
            || $approvalRequest->target_type !== $targetType
            || $approvalRequest->target_id !== $targetId) {
            throw ApprovalException::approvalTargetMismatch($approvalRequest->id);
        }

        return $approvalRequest;
    }

    public function consume(ApprovalRequest $approvalRequest, User $user, ?string $comment = null): ApprovalRequest
    {
        return DB::transaction(function () use ($approvalRequest, $user, $comment): ApprovalRequest {
            $approvalRequest->refresh();
            if ($approvalRequest->status !== 'approved' || $approvalRequest->consumed_at !== null) {
                throw ApprovalException::invalidStatus($approvalRequest->id, $approvalRequest->status, 'consumed');
            }

            $from = $approvalRequest->status;
            $approvalRequest->update([
                'status' => 'consumed',
                'consumed_at' => now(),
            ]);

            $this->recordAction($approvalRequest, 'consumed', $from, 'consumed', $user, $comment);
            $this->audit('approval.consumed', $approvalRequest, $user, $comment);

            return $approvalRequest->load(['requester', 'approver', 'actions.user']);
        });
    }

    private function finishAs(ApprovalRequest $approvalRequest, User $approver, string $status, string $comment): ApprovalRequest
    {
        return DB::transaction(function () use ($approvalRequest, $approver, $status, $comment): ApprovalRequest {
            $approvalRequest->refresh();
            if ($approvalRequest->status !== 'pending') {
                throw ApprovalException::invalidStatus($approvalRequest->id, $approvalRequest->status, $status);
            }

            $from = $approvalRequest->status;
            $approvalRequest->update([
                'status' => $status,
                'approved_by_user_id' => $approver->id,
                'rejected_at' => $status === 'rejected' ? now() : null,
                'returned_at' => $status === 'returned' ? now() : null,
                'return_reason' => $status === 'returned' ? $comment : null,
                'approver_comment' => $comment,
            ]);

            $this->recordAction($approvalRequest, $status, $from, $status, $approver, $comment);
            $this->audit('approval.'.$status, $approvalRequest, $approver, $comment);

            return $approvalRequest->load(['requester', 'approver', 'actions.user']);
        });
    }

    private function recordAction(
        ApprovalRequest $approvalRequest,
        string $action,
        ?string $fromStatus,
        string $toStatus,
        User $user,
        ?string $comment,
    ): void {
        $approvalRequest->actions()->create([
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'user_id' => $user->id,
            'comment' => $comment,
            'acted_at' => now(),
        ]);
    }

    private function audit(string $event, ApprovalRequest $approvalRequest, User $user, ?string $reason): void
    {
        $this->auditLogService->record(new AuditLogData(
            event: $event,
            auditable: $approvalRequest,
            user: $user,
            reason: $reason,
        ));
    }

    private function nextNumber(): string
    {
        return 'APR-'.now()->format('YmdHis').'-'.str_pad((string) (ApprovalRequest::count() + 1), 6, '0', STR_PAD_LEFT);
    }
}
