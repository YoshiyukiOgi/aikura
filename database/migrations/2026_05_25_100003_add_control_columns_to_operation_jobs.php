<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_jobs', function (Blueprint $table): void {
            $table->string('idempotency_key', 255)->nullable()->after('target_id');
            $table->foreignId('retry_of_operation_job_id')
                ->nullable()
                ->after('attempts')
                ->constrained('operation_jobs')
                ->nullOnDelete();
            $table->timestampTz('cancelled_at')->nullable()->after('failed_at');

            $table->index('idempotency_key');
            $table->index('retry_of_operation_job_id');
        });
    }

    public function down(): void
    {
        Schema::table('operation_jobs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('retry_of_operation_job_id');
            $table->dropIndex(['idempotency_key']);
            $table->dropColumn(['idempotency_key', 'cancelled_at']);
        });
    }
};
