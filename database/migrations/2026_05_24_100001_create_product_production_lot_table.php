<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_production_lot', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('production_lot_id')->constrained()->restrictOnDelete();
            $table->string('usage_type', 50)->default('shipment_candidate');
            $table->unsignedInteger('priority')->default(100);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->text('reason')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->unique(['product_id', 'production_lot_id', 'usage_type']);
            $table->index(['product_id', 'usage_type', 'is_active']);
            $table->index(['production_lot_id', 'usage_type', 'is_active']);
            $table->index(['priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_production_lot');
    }
};
