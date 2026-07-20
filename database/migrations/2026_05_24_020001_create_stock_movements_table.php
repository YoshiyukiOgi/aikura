<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 40)->default('draft');
            $table->string('movement_type', 50);
            $table->date('movement_date');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->string('source_type', 80)->nullable();
            $table->string('source_document_number', 80)->nullable();
            $table->unsignedInteger('source_line_no')->nullable();
            $table->foreignId('source_shipment_header_id')->nullable()->constrained('shipment_headers')->restrictOnDelete();
            $table->foreignId('source_shipment_line_id')->nullable()->constrained('shipment_lines')->restrictOnDelete();
            $table->foreignId('related_stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->string('lot_code', 80)->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->text('reason')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->index('status');
            $table->index('movement_type');
            $table->index('movement_date');
            $table->index(['product_id', 'stock_location_id']);
            $table->index(['stock_location_id', 'movement_date']);
            $table->index(['source_type', 'source_document_number']);
            $table->index('lot_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
