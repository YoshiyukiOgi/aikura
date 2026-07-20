<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_return_line_lots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_return_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_lot_id')->constrained('production_lots')->restrictOnDelete();
            $table->foreignId('stock_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->string('lot_code', 80)->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->unique(['sales_return_line_id', 'production_lot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_line_lots');
    }
};
