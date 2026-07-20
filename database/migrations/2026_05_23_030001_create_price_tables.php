<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 160);
            $table->string('price_type', 50);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();

            $table->index('price_type');
        });

        Schema::create('price_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_list_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('transaction_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('unit_price', 18, 4);
            $table->string('currency', 3)->default('JPY');
            $table->unsignedInteger('priority')->default(1000);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('rounding_method', 30)->default('round');
            $table->text('reason')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();

            $table->index(['product_id', 'effective_from', 'effective_to']);
            $table->index(['customer_id', 'product_id']);
            $table->index(['transaction_category_id', 'product_id']);
            $table->index(['priority', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_rules');
        Schema::dropIfExists('price_lists');
    }
};

