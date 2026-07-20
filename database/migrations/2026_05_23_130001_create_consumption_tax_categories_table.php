<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumption_tax_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 120);
            $table->string('taxability', 40)->index();
            $table->boolean('requires_tax_rate')->default(true);
            $table->boolean('is_reduced_rate')->default(false)->index();
            $table->boolean('is_export_exempt')->default(false)->index();
            $table->boolean('is_invoice_display_target')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumption_tax_categories');
    }
};
