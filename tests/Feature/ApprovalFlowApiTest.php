<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use Database\Seeders\FoundationPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalFlowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_approve_list_show_and_consume_approval(): void
    {
        [$requester, $approver] = $this->prepareUsers();

        $createResponse = $this->actingAs($requester)
            ->postJson('/api/v1/approval-requests', [
                'action_type' => ApprovalService::ACTION_DOCUMENT_CANCEL,
                'target_type' => 'shipment',
                'target_id' => '100',
                'reason' => 'cancel shipment after customer request',
                'payload' => ['route' => 'shipments.cancel'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.approval_request.status', 'pending')
            ->assertJsonPath('data.approval_request.action_type', ApprovalService::ACTION_DOCUMENT_CANCEL);

        $approvalRequestId = $createResponse->json('data.approval_request.id');

        $this->actingAs($approver)
            ->postJson("/api/v1/approval-requests/{$approvalRequestId}/approve", [
                'comment' => 'approved cancellation',
            ])
            ->assertOk()
            ->assertJsonPath('data.approval_request.status', 'approved')
            ->assertJsonPath('data.approval_request.approver_comment', 'approved cancellation');

        $this->actingAs($requester)
            ->getJson('/api/v1/approval-requests?action_type='.ApprovalService::ACTION_DOCUMENT_CANCEL)
            ->assertOk()
            ->assertJsonPath('data.approval_requests.0.id', $approvalRequestId);

        $this->actingAs($requester)
            ->getJson("/api/v1/approval-requests/{$approvalRequestId}")
            ->assertOk()
            ->assertJsonPath('data.approval_request.actions.0.action', 'requested')
            ->assertJsonPath('data.approval_request.actions.1.action', 'approved');

        $this->actingAs($approver)
            ->postJson("/api/v1/approval-requests/{$approvalRequestId}/consume", [
                'comment' => 'used by guarded execution',
            ])
            ->assertOk()
            ->assertJsonPath('data.approval_request.status', 'consumed');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'approval.consumed',
            'target_table' => 'approval_requests',
            'target_id' => (string) $approvalRequestId,
        ]);
    }

    public function test_approval_action_types_cover_stage_12_targets(): void
    {
        [$requester] = $this->prepareUsers();

        $actionTypes = [
            ApprovalService::ACTION_DOCUMENT_CANCEL,
            ApprovalService::ACTION_CLOSING_REOPEN,
            ApprovalService::ACTION_PRICE_CHANGE,
            ApprovalService::ACTION_TAX_CONFIRM,
            ApprovalService::ACTION_ROLE_PERMISSION_CHANGE,
        ];

        foreach ($actionTypes as $index => $actionType) {
            $this->actingAs($requester)
                ->postJson('/api/v1/approval-requests', [
                    'action_type' => $actionType,
                    'target_type' => 'stage12',
                    'target_id' => (string) ($index + 1),
                    'reason' => 'stage 12 approval request',
                ])
                ->assertCreated()
                ->assertJsonPath('data.approval_request.action_type', $actionType);
        }

        $this->assertSame(5, ApprovalRequest::count());
    }

    public function test_returned_request_can_be_resubmitted_and_approved(): void
    {
        [$requester, $approver] = $this->prepareUsers();

        $approvalRequestId = $this->createApprovalRequest($requester, ApprovalService::ACTION_PRICE_CHANGE, 'price_rule', '10');

        $this->actingAs($approver)
            ->postJson("/api/v1/approval-requests/{$approvalRequestId}/return", [
                'comment' => 'price reason is not enough',
            ])
            ->assertOk()
            ->assertJsonPath('data.approval_request.status', 'returned')
            ->assertJsonPath('data.approval_request.return_reason', 'price reason is not enough');

        $this->actingAs($requester)
            ->postJson("/api/v1/approval-requests/{$approvalRequestId}/resubmit", [
                'reason' => 'added price change reason',
                'payload' => ['unit_price' => '1200.0000'],
            ])
            ->assertOk()
            ->assertJsonPath('data.approval_request.status', 'pending')
            ->assertJsonPath('data.approval_request.payload.unit_price', '1200.0000');

        $this->actingAs($approver)
            ->postJson("/api/v1/approval-requests/{$approvalRequestId}/approve")
            ->assertOk()
            ->assertJsonPath('data.approval_request.status', 'approved');
    }

    public function test_rejected_request_cannot_be_approved_and_requester_cannot_self_approve(): void
    {
        [$requester, $approver] = $this->prepareUsers();

        $rejectedId = $this->createApprovalRequest($requester, ApprovalService::ACTION_TAX_CONFIRM, 'liquor_tax_monthly_filing', '2026-06');

        $this->actingAs($approver)
            ->postJson("/api/v1/approval-requests/{$rejectedId}/reject", [
                'comment' => 'filing amount must be checked',
            ])
            ->assertOk()
            ->assertJsonPath('data.approval_request.status', 'rejected');

        $this->actingAs($approver)
            ->postJson("/api/v1/approval-requests/{$rejectedId}/approve")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'business_rule_violation');

        $selfApprovalId = $this->createApprovalRequest($requester, ApprovalService::ACTION_ROLE_PERMISSION_CHANGE, 'role', 'admin');

        $this->actingAs($requester)
            ->postJson("/api/v1/approval-requests/{$selfApprovalId}/approve")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'business_rule_violation');
    }

    public function test_approved_request_is_required_before_guarded_execution(): void
    {
        [$requester, $approver] = $this->prepareUsers();

        $pendingId = $this->createApprovalRequest($requester, ApprovalService::ACTION_CLOSING_REOPEN, 'stock_monthly_balance', '2026-06');

        $this->actingAs($approver)
            ->postJson("/api/v1/approval-requests/{$pendingId}/consume")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'business_rule_violation');

        $approvalRequest = ApprovalRequest::findOrFail($pendingId);
        $this->expectException(\DomainException::class);
        app(ApprovalService::class)->assertApprovedFor(
            approvalRequestId: $approvalRequest->id,
            actionType: ApprovalService::ACTION_CLOSING_REOPEN,
            targetType: 'stock_monthly_balance',
            targetId: '2026-06',
        );
    }

    public function test_user_without_approval_permission_cannot_request(): void
    {
        $this->seed(FoundationPermissionSeeder::class);
        $user = $this->createUser('approval-limited@example.com');

        $this->actingAs($user)
            ->postJson('/api/v1/approval-requests', [
                'action_type' => ApprovalService::ACTION_DOCUMENT_CANCEL,
                'target_type' => 'shipment',
                'target_id' => '100',
                'reason' => 'approval without permission',
            ])
            ->assertForbidden()
            ->assertJsonPath('permission', 'approval.request');
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function prepareUsers(): array
    {
        $this->seed(FoundationPermissionSeeder::class);

        $requester = $this->createUser('approval-requester@example.com');
        $approver = $this->createUser('approval-approver@example.com');
        $adminRole = Role::where('code', 'admin')->firstOrFail();

        $requester->roles()->attach($adminRole);
        $approver->roles()->attach($adminRole);

        return [$requester, $approver];
    }

    private function createApprovalRequest(User $requester, string $actionType, string $targetType, string $targetId): int
    {
        return $this->actingAs($requester)
            ->postJson('/api/v1/approval-requests', [
                'action_type' => $actionType,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'reason' => 'approval request reason',
            ])
            ->assertCreated()
            ->json('data.approval_request.id');
    }

    private function createUser(string $email): User
    {
        $employee = Employee::create([
            'employee_code' => 'APPROVAL'.str_pad((string) (Employee::count() + 1), 3, '0', STR_PAD_LEFT),
            'name' => 'Approval Employee',
            'email' => 'employee-'.$email,
        ]);

        return User::create([
            'employee_id' => $employee->id,
            'name' => 'Approval User',
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);
    }
}
