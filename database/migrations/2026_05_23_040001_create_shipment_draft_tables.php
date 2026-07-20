<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_headers', function (Blueprint $table): void {
            $table->id();
            $table->string('document_number', 80)->unique();
            $table->string('status', 40)->default('draft')->index();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('transaction_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('settlement_receivable_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('billing_cycle_id')->constrained()->restrictOnDelete();
            $table->date('document_date');
            $table->date('order_date')->nullable();
            $table->date('scheduled_shipment_date')->nullable();
            $table->date('actual_shipment_date')->nullable();
            $table->date('sales_recorded_on')->nullable();
            $table->date('billing_target_date')->nullable();
            $table->date('liquor_tax_transfer_date')->nullable();
            $table->foreignId('source_shipment_header_id')->nullable()->constrained('shipment_headers')->restrictOnDelete();
            $table->text('correction_reason')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->index('document_date');
            $table->index('billing_target_date');
            $table->index('scheduled_shipment_date');
            $table->index(['customer_id', 'document_date']);
        });

        Schema::create('shipment_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_header_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->unique(['shipment_header_id', 'line_no']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_lines');
        Schema::dropIfExists('shipment_headers');
    }
};

