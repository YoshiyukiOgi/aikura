<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receivable_monthly_balances', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 40)->default('draft')->index();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('customer_code', 80);
            $table->string('customer_name', 255);
            $table->decimal('scheduled_amount', 18, 2)->default(0);
            $table->decimal('received_amount', 18, 2)->default(0);
            $table->decimal('outstanding_amount', 18, 2)->default(0);
            $table->unsignedInteger('open_schedule_count')->default(0);
            $table->unsignedInteger('partial_schedule_count')->default(0);
            $table->unsignedInteger('closed_schedule_count')->default(0);
            $table->timestampTz('calculated_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->text('reason')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->unique(['year', 'month', 'customer_id']);
            $table->index(['customer_id', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receivable_monthly_balances');
    }
};
