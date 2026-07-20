<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('job_type', 120)->index();
            $table->string('status', 40)->index();
            $table->string('target_type', 120)->nullable()->index();
            $table->string('target_id', 120)->nullable()->index();
            $table->json('payload')->nullable();
            $table->unsignedInteger('attempts')->default(1);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->text('reason')->nullable();
            $table->timestampsTz();

            $table->index(['job_type', 'status']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_jobs');
    }
};
