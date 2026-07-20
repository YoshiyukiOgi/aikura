<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquor_tax_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 120);
            $table->string('taxability', 40)->default('taxable')->index();
            $table->string('aggregate0', 120)->nullable();
            $table->string('aggregate1', 120)->nullable();
            $table->string('aggregate2', 120)->nullable();
            $table->string('aggregate3', 120)->nullable();
            $table->unsignedInteger('print_order')->default(0);
            $table->decimal('reduction_rate', 8, 4)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();

            $table->index(['taxability', 'print_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquor_tax_categories');
    }
};
