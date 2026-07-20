<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_headers', function (Blueprint $table): void {
            $table->id();
            $table->string('invoice_number', 80)->nullable()->unique();
            $table->string('status', 40)->default('draft')->index();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('billing_cycle_id')->constrained()->restrictOnDelete();
            $table->date('invoice_date');
            $table->date('billing_period_start')->nullable();
            $table->date('billing_period_end')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('subtotal_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->index(['customer_id', 'invoice_date']);
            $table->index(['billing_period_start', 'billing_period_end']);
        });

        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_header_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_header_id')->constrained()->restrictOnDelete();
            $table->foreignId('shipment_line_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_code', 80);
            $table->string('product_name', 160);
            $table->string('display_name', 160);
            $table->decimal('quantity', 18, 4);
            $table->string('unit_code', 50);
            $table->string('unit_name', 80);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('amount', 18, 2)->default(0);
            $table->decimal('tax_rate', 10, 4)->nullable();
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->unique('shipment_line_id');
            $table->unique(['invoice_header_id', 'line_no']);
            $table->index('shipment_header_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoice_headers');
    }
};

