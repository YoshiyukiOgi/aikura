<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ApprovalRequest;
use App\Services\Approvals\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController extends ApiController
{
    public function __construct(
        private readonly ApprovalService $approvalService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:40'],
            'action_type' => ['nullable', 'string', 'max:80'],
            'target_type' => ['nullable', 'string', 'max:120'],
            'target_id' => ['nullable', 'string', 'max:80'],
        ]);

        $query = ApprovalRequest::query()
            ->with(['requester', 'approver', 'actions.user'])
            ->latest('id');

        foreach (['status', 'action_type', 'target_type', 'target_id'] as $column) {
            if (! empty($validated[$column])) {
                $query->where($column, $validated[$column]);
            }
        }

        return $this->ok([
            'approval_requests' => $query
                ->limit(50)
                ->get()
                ->map(fn (ApprovalRequest $approvalRequest): array => $this->serializeApprovalRequest($approvalRequest))
                ->values()
                ->all(),
        ]);
    }

    public function show(ApprovalRequest $approvalRequest): JsonResponse
    {
        return $this->ok([
            'approval_request' => $this->serializeApprovalRequest($approvalRequest->load(['requester', 'approver', 'actions.user'])),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action_type' => ['required', 'string', 'max:80'],
            'target_type' => ['required', 'string', 'max:120'],
            'target_id' => ['required', 'string', 'max:80'],
            'reason' => ['required', 'string', 'max:1000'],
            'payload' => ['nullable', 'array'],
        ]);

        $approvalRequest = $this->approvalService->request(
            requester: $request->user(),
            actionType: $validated['action_type'],
            targetType: $validated['target_type'],
            targetId: $validated['target_id'],
            reason: $validated['reason'],
            payload: $validated['payload'] ?? [],
        );

        return $this->created([
            'approval_request' => $this->serializeApprovalRequest($approvalRequest),
        ]);
    }

    public function approve(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $validated = $request->validate([
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $approved = $this->approvalService->approve($approvalRequest, $request->user(), $validated['comment'] ?? null);

        return $this->ok(['approval_request' => $this->serializeApprovalRequest($approved)]);
    }

    public function reject(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:1000'],
        ]);

        $rejected = $this->approvalService->reject($approvalRequest, $request->user(), $validated['comment']);

        return $this->ok(['approval_request' => $this->serializeApprovalRequest($rejected)]);
    }

    public function returnForCorrection(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:1000'],
        ]);

        $returned = $this->approvalService->returnForCorrection($approvalRequest, $request->user(), $validated['comment']);

        return $this->ok(['approval_request' => $this->serializeApprovalRequest($returned)]);
    }

    public function resubmit(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'payload' => ['nullable', 'array'],
        ]);

        $resubmitted = $this->approvalService->resubmit(
            $approvalRequest,
            $request->user(),
            $validated['reason'],
            $validated['payload'] ?? [],
        );

        return $this->ok(['approval_request' => $this->serializeApprovalRequest($resubmitted)]);
    }

    public function consume(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $validated = $request->validate([
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $consumed = $this->approvalService->consume($approvalRequest, $request->user(), $validated['comment'] ?? null);

        return $this->ok(['approval_request' => $this->serializeApprovalRequest($consumed)]);
    }

    private function serializeApprovalRequest(ApprovalRequest $approvalRequest): array
    {
        return [
            'id' => $approvalRequest->id,
            'approval_number' => $approvalRequest->approval_number,
            'status' => $approvalRequest->status,
            'action_type' => $approvalRequest->action_type,
            'target_type' => $approvalRequest->target_type,
            'target_id' => $approvalRequest->target_id,
            'requested_by_user_id' => $approvalRequest->requested_by_user_id,
            'requested_by_user_name' => $approvalRequest->requester?->name,
            'approved_by_user_id' => $approvalRequest->approved_by_user_id,
            'approved_by_user_name' => $approvalRequest->approver?->name,
            'requested_at' => $approvalRequest->requested_at?->toISOString(),
            'approved_at' => $approvalRequest->approved_at?->toISOString(),
            'rejected_at' => $approvalRequest->rejected_at?->toISOString(),
            'returned_at' => $approvalRequest->returned_at?->toISOString(),
            'consumed_at' => $approvalRequest->consumed_at?->toISOString(),
            'payload' => $approvalRequest->payload,
            'reason' => $approvalRequest->reason,
            'approver_comment' => $approvalRequest->approver_comment,
            'return_reason' => $approvalRequest->return_reason,
            'actions' => $approvalRequest->actions
                ->map(fn ($action): array => [
                    'id' => $action->id,
                    'action' => $action->action,
                    'from_status' => $action->from_status,
                    'to_status' => $action->to_status,
                    'user_id' => $action->user_id,
                    'user_name' => $action->user?->name,
                    'comment' => $action->comment,
                    'acted_at' => $action->acted_at?->toISOString(),
                ])
                ->values()
                ->all(),
        ];
    }
}
