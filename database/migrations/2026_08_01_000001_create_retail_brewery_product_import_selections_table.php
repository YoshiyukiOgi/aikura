<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('retail.database.connection', 'retail'))
            ->create('retail_brewery_product_import_selections', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('brewery_product_id')->unique();
                $table->boolean('is_selected')->default(false)->index();
                $table->timestampsTz();
            });
    }

    public function down(): void
    {
        Schema::connection(config('retail.database.connection', 'retail'))
            ->dropIfExists('retail_brewery_product_import_selections');
    }
};
