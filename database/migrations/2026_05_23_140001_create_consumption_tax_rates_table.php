<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumption_tax_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('consumption_tax_category_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->decimal('rate', 8, 4);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();

            $table->unique(['consumption_tax_category_id', 'effective_from']);
            $table->index(['consumption_tax_category_id', 'effective_from', 'effective_to'], 'tax_rates_category_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumption_tax_rates');
    }
};
