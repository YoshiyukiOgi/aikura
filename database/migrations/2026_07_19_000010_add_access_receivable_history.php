<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_receivable_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('access_migration_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('legacy_access_payment_id', 80);
            $table->date('entry_date');
            $table->unsignedSmallInteger('billing_year');
            $table->unsignedTinyInteger('billing_month');
            $table->decimal('signed_amount', 18, 2);
            $table->string('entry_type', 40)->index();
            $table->boolean('is_previous_month_bill')->default(false);
            $table->boolean('is_transfer_fee')->default(false);
            $table->text('description')->nullable();
            $table->jsonb('source_payload');
            $table->string('source_payload_sha256', 64);
            $table->timestampsTz();

            $table->unique(['access_migration_batch_id', 'legacy_access_payment_id'], 'access_receivable_ledger_legacy_unique');
            $table->index(['customer_id', 'entry_date']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('access_receivable_ledger_entry_id')
                ->nullable()
                ->unique()
                ->after('id')
                ->constrained()
                ->restrictOnDelete();
            $table->boolean('is_legacy_history')->default(false)->after('access_receivable_ledger_entry_id');
        });

        Schema::create('opening_receivable_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('access_migration_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('status', 40)->default('calculated')->index();
            $table->date('as_of_date');
            $table->unsignedInteger('source_sales_count')->default(0);
            $table->unsignedInteger('source_ledger_entry_count')->default(0);
            $table->decimal('source_sales_amount', 18, 2)->default(0);
            $table->decimal('source_ledger_amount', 18, 2)->default(0);
            $table->decimal('calculated_balance_amount', 18, 2)->default(0);
            $table->decimal('statement_balance_amount', 18, 2)->nullable();
            $table->decimal('adjustment_amount', 18, 2)->default(0);
            $table->decimal('opening_balance_amount', 18, 2)->default(0);
            $table->timestampTz('calculated_at');
            $table->timestampTz('reconciled_at')->nullable();
            $table->text('reconciliation_note')->nullable();
            $table->timestampsTz();

            $table->unique(['access_migration_batch_id', 'customer_id'], 'opening_receivable_batch_customer_unique');
            $table->index(['customer_id', 'as_of_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_receivable_balances');

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('access_receivable_ledger_entry_id');
            $table->dropColumn('is_legacy_history');
        });

        Schema::dropIfExists('access_receivable_ledger_entries');
    }
};
