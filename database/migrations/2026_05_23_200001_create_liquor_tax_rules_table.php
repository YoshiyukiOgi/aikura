<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquor_tax_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('liquor_tax_category_id')->constrained()->restrictOnDelete();
            $table->string('code', 100)->unique();
            $table->string('name', 160);
            $table->string('calculation_method', 80)->default('fixed_per_kl')->index();
            $table->decimal('tax_per_kl', 18, 4)->default(0);
            $table->decimal('alcohol_percentage_min', 5, 2)->nullable();
            $table->decimal('alcohol_percentage_max', 5, 2)->nullable();
            $table->decimal('base_alcohol_percentage', 5, 2)->nullable();
            $table->decimal('additional_tax_per_kl_per_percent', 18, 4)->nullable();
            $table->decimal('reduction_rate', 8, 4)->default(0);
            $table->string('special_provision_code', 80)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();

            $table->index(['liquor_tax_category_id', 'effective_from', 'effective_to'], 'liquor_tax_rules_category_period_index');
            $table->index(['liquor_tax_category_id', 'alcohol_percentage_min', 'alcohol_percentage_max'], 'liquor_tax_rules_alcohol_range_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquor_tax_rules');
    }
};
