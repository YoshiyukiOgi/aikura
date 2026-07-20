<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_header_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('status', 40)->default('open')->index();
            $table->date('expected_payment_date');
            $table->decimal('scheduled_amount', 18, 2);
            $table->decimal('received_amount', 18, 2)->default(0);
            $table->decimal('outstanding_amount', 18, 2);
            $table->timestampTz('closed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->index(['customer_id', 'expected_payment_date']);
            $table->index(['expected_payment_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_schedules');
    }
};
