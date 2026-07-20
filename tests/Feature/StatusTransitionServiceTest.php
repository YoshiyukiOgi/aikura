<?php

namespace Tests\Feature;

use App\Exceptions\StateMachine\InvalidStatusTransitionException;
use App\Services\StateMachine\StatusTransitionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StatusTransitionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('workflow_test_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 40);
            $table->timestampsTz();
        });
    }

    public function test_allowed_transition_updates_status_and_records_audit_log(): void
    {
        $document = WorkflowTestDocument::create([
            'status' => 'draft',
        ]);

        $result = app(StatusTransitionService::class)->transition(
            model: $document,
            machine: 'shipment',
            to: 'confirmed',
            reason: '出荷伝票を確定するため',
        );

        $this->assertSame('draft', $result->from);
        $this->assertSame('confirmed', $result->to);
        $this->assertSame('confirmed', $document->refresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'shipment.status_changed',
            'target_table' => 'workflow_test_documents',
            'target_id' => (string) $document->id,
            'reason' => '出荷伝票を確定するため',
        ]);

        $this->assertSame(['status' => 'draft'], $result->auditLog?->before_values);
        $this->assertSame(['status' => 'confirmed'], $result->auditLog?->after_values);
    }

    public function test_forbidden_transition_throws_exception_and_does_not_update_status(): void
    {
        $document = WorkflowTestDocument::create([
            'status' => 'draft',
        ]);

        $this->expectException(InvalidStatusTransitionException::class);

        try {
            app(StatusTransitionService::class)->transition(
                model: $document,
                machine: 'shipment',
                to: 'closed',
                reason: '不正な遷移の確認',
            );
        } finally {
            $this->assertSame('draft', $document->refresh()->status);
            $this->assertDatabaseMissing('audit_logs', [
                'event' => 'shipment.status_changed',
                'target_table' => 'workflow_test_documents',
                'target_id' => (string) $document->id,
            ]);
        }
    }

    public function test_unknown_state_machine_throws_exception(): void
    {
        $document = WorkflowTestDocument::create([
            'status' => 'draft',
        ]);

        $this->expectException(InvalidStatusTransitionException::class);

        app(StatusTransitionService::class)->transition(
            model: $document,
            machine: 'unknown',
            to: 'confirmed',
        );
    }
}

class WorkflowTestDocument extends Model
{
    protected $table = 'workflow_test_documents';

    protected $guarded = [];
}

