<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_locations', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 120);
            $table->string('location_type', 40);
            $table->foreignId('parent_stock_location_id')->nullable()->constrained('stock_locations')->nullOnDelete();
            $table->boolean('is_default_shipping_location')->default(false);
            $table->boolean('is_default_receiving_location')->default(false);
            $table->boolean('is_inventory_managed')->default(true);
            $table->boolean('is_shippable')->default(true);
            $table->boolean('is_sellable')->default(true);
            $table->boolean('is_tax_relevant')->default(true);
            $table->string('postal_code', 20)->nullable();
            $table->string('address1', 255)->nullable();
            $table->string('address2', 255)->nullable();
            $table->string('phone', 40)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();

            $table->index('location_type');
            $table->index('parent_stock_location_id');
            $table->index('is_default_shipping_location');
            $table->index('is_default_receiving_location');
            $table->index('is_inventory_managed');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_locations');
    }
};
