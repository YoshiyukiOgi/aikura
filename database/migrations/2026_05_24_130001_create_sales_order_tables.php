<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number', 80)->unique();
            $table->string('status', 40)->default('received')->index();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('transaction_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('settlement_receivable_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('billing_cycle_id')->constrained()->restrictOnDelete();
            $table->date('order_date');
            $table->date('requested_shipment_date')->nullable();
            $table->date('requested_delivery_date')->nullable();
            $table->date('billing_target_date')->nullable();
            $table->string('customer_order_number', 120)->nullable()->index();
            $table->string('source_type', 80)->nullable()->index();
            $table->string('source_reference', 120)->nullable()->index();
            $table->text('cancelled_reason')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->index('order_date');
            $table->index('requested_shipment_date');
            $table->index(['customer_id', 'order_date']);
        });

        Schema::create('sales_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('remaining_quantity', 18, 4);
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->unique(['sales_order_id', 'line_no']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_lines');
        Schema::dropIfExists('sales_orders');
    }
};
