<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection(config('retail.database.connection', 'retail'));

        $schema->create('retail_inventory_stocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('retail_product_id')->unique()->constrained('retail_products')->cascadeOnDelete();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->timestampsTz();
        });

        $schema->create('retail_inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('retail_product_id')->constrained('retail_products')->restrictOnDelete();
            $table->string('movement_type', 30)->index();
            $table->decimal('quantity', 14, 3);
            $table->decimal('stock_after', 14, 3);
            $table->nullableMorphs('source');
            $table->timestampTz('occurred_at')->index();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->index(['retail_product_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        $schema = Schema::connection(config('retail.database.connection', 'retail'));

        $schema->dropIfExists('retail_inventory_movements');
        $schema->dropIfExists('retail_inventory_stocks');
    }
};
