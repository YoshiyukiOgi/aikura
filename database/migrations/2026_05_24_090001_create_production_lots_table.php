<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_lots', function (Blueprint $table): void {
            $table->id();
            $table->string('lot_code', 80)->unique();
            $table->string('display_name', 160);
            $table->string('status', 40)->default('active')->index();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('stock_location_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('production_date')->nullable();
            $table->date('bottling_date')->nullable();
            $table->date('best_before_date')->nullable();
            $table->string('tank_code', 80)->nullable();
            $table->string('rice_variety', 120)->nullable();
            $table->decimal('rice_polishing_ratio', 5, 2)->nullable();
            $table->string('production_method', 120)->nullable();
            $table->string('storage_condition', 120)->nullable();
            $table->string('external_system_code', 120)->nullable()->index();
            $table->string('legacy_lot_text', 160)->nullable()->index();
            $table->text('search_key')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampTz('disabled_at')->nullable();
            $table->timestampsTz();

            $table->index('display_name');
            $table->index('production_date');
            $table->index('bottling_date');
            $table->index('best_before_date');
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreignId('production_lot_id')
                ->nullable()
                ->after('related_stock_movement_id')
                ->constrained('production_lots')
                ->restrictOnDelete();

            $table->index('production_lot_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('production_lot_id');
        });

        Schema::dropIfExists('production_lots');
    }
};
