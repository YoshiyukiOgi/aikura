<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_lot_allocations', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 40)->default('allocated')->index();
            $table->foreignId('shipment_header_id')->constrained('shipment_headers')->cascadeOnDelete();
            $table->foreignId('shipment_line_id')->constrained('shipment_lines')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('production_lot_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->timestampTz('allocated_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->text('reason')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->index(['shipment_line_id', 'status']);
            $table->index(['production_lot_id', 'product_id', 'stock_location_id', 'unit_id'], 'shipment_lot_allocations_lot_stock_index');
            $table->index(['product_id', 'stock_location_id', 'unit_id'], 'shipment_lot_allocations_product_stock_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_lot_allocations');
    }
};
