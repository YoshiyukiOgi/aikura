<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('non_sales_stock_operation_headers', function (Blueprint $table): void {
            $table->id();
            $table->string('operation_number', 80)->unique();
            $table->string('status', 40)->default('confirmed')->index();
            $table->string('operation_type', 50)->index();
            $table->date('operation_date')->index();
            $table->foreignId('source_sales_return_header_id')->nullable()->constrained('sales_return_headers')->restrictOnDelete();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->text('reason');
            $table->text('note')->nullable();
            $table->timestampsTz();
        });

        Schema::create('non_sales_stock_operation_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('non_sales_stock_operation_header_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_sales_return_line_id')->nullable()->constrained('sales_return_lines')->restrictOnDelete();
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->restrictOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->foreignId('production_lot_id')->nullable()->constrained('production_lots')->restrictOnDelete();
            $table->string('lot_code', 80)->nullable();
            $table->text('reason')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->unique(['non_sales_stock_operation_header_id', 'line_no'], 'non_sales_stock_operation_line_no_unique');
            $table->index('source_sales_return_line_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('non_sales_stock_operation_lines');
        Schema::dropIfExists('non_sales_stock_operation_headers');
    }
};
