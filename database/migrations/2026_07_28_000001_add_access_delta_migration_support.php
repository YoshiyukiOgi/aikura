<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_migration_batches', function (Blueprint $table): void {
            $table->foreignId('baseline_batch_id')
                ->nullable()
                ->after('id')
                ->constrained('access_migration_batches')
                ->nullOnDelete();
            $table->jsonb('delta_summary')->nullable()->after('validation_summary');
            $table->timestampTz('delta_planned_at')->nullable()->after('delta_summary');
            $table->timestampTz('delta_applied_at')->nullable()->after('delta_planned_at');
        });

        Schema::create('access_migration_deltas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('access_migration_batches')->cascadeOnDelete();
            $table->foreignId('baseline_batch_id')->constrained('access_migration_batches')->restrictOnDelete();
            $table->string('source_table');
            $table->string('source_key', 500);
            $table->string('change_type', 20);
            $table->foreignId('current_staging_row_id')->nullable()->constrained('access_migration_staging_rows')->cascadeOnDelete();
            $table->foreignId('baseline_staging_row_id')->nullable()->constrained('access_migration_staging_rows')->nullOnDelete();
            $table->char('current_payload_sha256', 64)->nullable();
            $table->char('baseline_payload_sha256', 64)->nullable();
            $table->string('apply_status', 20)->default('planned');
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->unique(['batch_id', 'source_table', 'source_key'], 'access_migration_delta_source_unique');
            $table->index(['batch_id', 'change_type']);
            $table->index(['batch_id', 'apply_status']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->string('legacy_access_payment_id', 80)
                ->nullable()
                ->after('access_receivable_ledger_entry_id');
        });

        DB::table('payments')
            ->whereNull('payments.legacy_access_payment_id')
            ->whereNotNull('payments.access_receivable_ledger_entry_id')
            ->update([
                'legacy_access_payment_id' => DB::raw(
                    '(SELECT legacy_access_payment_id FROM access_receivable_ledger_entries WHERE access_receivable_ledger_entries.id = payments.access_receivable_ledger_entry_id)'
                ),
            ]);

        Schema::table('payments', function (Blueprint $table): void {
            $table->unique('legacy_access_payment_id', 'payments_legacy_access_payment_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('payments_legacy_access_payment_unique');
            $table->dropColumn('legacy_access_payment_id');
        });

        Schema::dropIfExists('access_migration_deltas');

        Schema::table('access_migration_batches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('baseline_batch_id');
            $table->dropColumn(['delta_summary', 'delta_planned_at', 'delta_applied_at']);
        });
    }
};
