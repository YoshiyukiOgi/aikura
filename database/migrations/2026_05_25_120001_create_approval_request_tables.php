<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('approval_number', 80)->unique();
            $table->string('status', 40)->default('pending')->index();
            $table->string('action_type', 80)->index();
            $table->string('target_type', 120);
            $table->string('target_id', 80);
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('requested_at');
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->timestampTz('returned_at')->nullable();
            $table->timestampTz('consumed_at')->nullable();
            $table->jsonb('payload')->nullable();
            $table->text('reason');
            $table->text('approver_comment')->nullable();
            $table->text('return_reason')->nullable();
            $table->timestampsTz();

            $table->index(['action_type', 'target_type', 'target_id']);
            $table->index(['requested_by_user_id', 'status']);
        });

        Schema::create('approval_request_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('approval_request_id')->constrained()->cascadeOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('comment')->nullable();
            $table->timestampTz('acted_at');
            $table->timestampsTz();

            $table->index(['approval_request_id', 'acted_at']);
            $table->index(['action', 'acted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_request_actions');
        Schema::dropIfExists('approval_requests');
    }
};
