<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('retail.database.connection', 'retail'))
            ->table('retail_invoices', function (Blueprint $table): void {
                $table->timestampTz('cancelled_at')->nullable()->after('status');
                $table->text('cancel_reason')->nullable()->after('cancelled_at');
            });

        Schema::connection(config('retail.database.connection', 'retail'))
            ->table('retail_payments', function (Blueprint $table): void {
                $table->string('status', 30)->default('posted')->index()->after('payment_method');
                $table->foreignId('original_retail_payment_id')->nullable()->after('status')->constrained('retail_payments')->nullOnDelete();
                $table->string('adjustment_type', 30)->nullable()->after('original_retail_payment_id');
                $table->text('adjustment_reason')->nullable()->after('note');
            });
    }

    public function down(): void
    {
        Schema::connection(config('retail.database.connection', 'retail'))
            ->table('retail_payments', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('original_retail_payment_id');
                $table->dropColumn(['status', 'adjustment_type', 'adjustment_reason']);
            });

        Schema::connection(config('retail.database.connection', 'retail'))
            ->table('retail_invoices', function (Blueprint $table): void {
                $table->dropColumn(['cancelled_at', 'cancel_reason']);
            });
    }
};
