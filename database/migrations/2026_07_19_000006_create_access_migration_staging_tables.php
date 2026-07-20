<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_migration_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 30)->default('staging');
            $table->string('source_file_name');
            $table->text('source_file_path');
            $table->char('source_sha256', 64);
            $table->unsignedBigInteger('source_size');
            $table->timestampTz('source_last_modified_at')->nullable();
            $table->string('extractor_version', 30);
            $table->unsignedSmallInteger('package_version');
            $table->unsignedInteger('source_table_count')->default(0);
            $table->unsignedBigInteger('source_row_count')->default(0);
            $table->unsignedInteger('staged_table_count')->default(0);
            $table->unsignedBigInteger('staged_row_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->jsonb('manifest');
            $table->jsonb('validation_summary')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index(['source_sha256', 'status']);
            $table->index('created_at');
        });

        Schema::create('access_migration_tables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('access_migration_batches')->cascadeOnDelete();
            $table->string('source_table');
            $table->string('package_file');
            $table->char('package_sha256', 64);
            $table->unsignedBigInteger('source_row_count');
            $table->unsignedBigInteger('staged_row_count')->default(0);
            $table->jsonb('source_columns');
            $table->string('status', 30)->default('pending');
            $table->timestampsTz();

            $table->unique(['batch_id', 'source_table']);
        });

        Schema::create('access_migration_staging_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('access_migration_batches')->cascadeOnDelete();
            $table->string('source_table');
            $table->unsignedBigInteger('source_row_number');
            $table->string('source_key', 500);
            $table->jsonb('payload');
            $table->char('payload_sha256', 64);
            $table->string('status', 30)->default('staged');
            $table->string('target_table')->nullable();
            $table->string('target_id', 100)->nullable();
            $table->timestampsTz();

            $table->unique(['batch_id', 'source_table', 'source_row_number'], 'access_migration_row_number_unique');
            $table->unique(['batch_id', 'source_table', 'source_key'], 'access_migration_source_key_unique');
            $table->index(['batch_id', 'status']);
        });

        Schema::create('access_migration_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('access_migration_batches')->cascadeOnDelete();
            $table->string('severity', 20);
            $table->string('issue_code', 100);
            $table->string('source_table')->nullable();
            $table->unsignedBigInteger('source_row_number')->nullable();
            $table->string('source_key', 500)->nullable();
            $table->string('source_field')->nullable();
            $table->text('message');
            $table->jsonb('context')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['batch_id', 'severity']);
            $table->index(['batch_id', 'issue_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_migration_issues');
        Schema::dropIfExists('access_migration_staging_rows');
        Schema::dropIfExists('access_migration_tables');
        Schema::dropIfExists('access_migration_batches');
    }
};
