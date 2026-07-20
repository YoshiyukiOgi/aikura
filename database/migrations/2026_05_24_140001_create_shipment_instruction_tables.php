<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_instructions', function (Blueprint $table): void {
            $table->id();
            $table->string('instruction_number', 80)->unique();
            $table->string('status', 40)->default('instructed')->index();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('instruction_date');
            $table->date('scheduled_shipment_date')->nullable();
            $table->foreignId('stock_location_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('cancelled_reason')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->index('instruction_date');
            $table->index('scheduled_shipment_date');
            $table->index(['customer_id', 'instruction_date']);
        });

        Schema::create('shipment_instruction_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_instruction_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignId('sales_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_order_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->unique(['shipment_instruction_id', 'line_no'], 'shipment_instruction_lines_line_unique');
            $table->index('sales_order_id');
            $table->index('sales_order_line_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_instruction_lines');
        Schema::dropIfExists('shipment_instructions');
    }
};
