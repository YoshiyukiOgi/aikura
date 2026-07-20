<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('status', 40)->default('allocated')->index();
            $table->date('payment_date');
            $table->string('payment_method', 80)->default('bank_transfer');
            $table->decimal('amount', 18, 2);
            $table->string('reference_number', 120)->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->index(['customer_id', 'payment_date']);
            $table->index('payment_method');
        });

        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_schedule_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_header_id')->constrained()->restrictOnDelete();
            $table->decimal('allocated_amount', 18, 2);
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->index('payment_schedule_id');
            $table->index('invoice_header_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
    }
};
