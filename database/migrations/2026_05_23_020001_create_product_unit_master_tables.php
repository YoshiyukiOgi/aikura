<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 80);
            $table->string('symbol', 30)->nullable();
            $table->string('unit_type', 50)->default('count');
            $table->unsignedTinyInteger('decimal_scale')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();

            $table->index('unit_type');
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('product_code', 80)->unique();
            $table->string('product_type', 50);
            $table->string('name', 160);
            $table->string('name_kana', 160)->nullable();
            $table->string('display_name', 160);
            $table->string('brand_name', 120)->nullable();
            $table->string('series_name', 120)->nullable();
            $table->string('category_name', 120)->nullable();
            $table->foreignId('base_unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('sales_unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->foreignId('inventory_unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->decimal('capacity_value', 18, 4)->nullable();
            $table->foreignId('capacity_unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->decimal('alcohol_percentage', 5, 2)->nullable();
            $table->boolean('is_alcohol')->default(false);
            $table->boolean('is_sales_available')->default(true)->index();
            $table->boolean('is_inventory_managed')->default(true);
            $table->text('search_key')->nullable();
            $table->string('legacy_code', 80)->nullable()->index();
            $table->string('legacy_name', 160)->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampTz('disabled_at')->nullable();
            $table->timestampsTz();

            $table->index('product_type');
            $table->index('name');
            $table->index('name_kana');
            $table->index('display_name');
            $table->index('brand_name');
            $table->index('category_name');
        });

        Schema::create('sake_product_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('liquor_tax_category_code', 80)->nullable();
            $table->string('liquor_type_name', 120)->nullable();
            $table->text('ingredients')->nullable();
            $table->decimal('rice_polishing_ratio', 5, 2)->nullable();
            $table->string('production_method', 120)->nullable();
            $table->boolean('is_unpasteurized')->default(false);
            $table->timestampsTz();
        });

        Schema::create('kasu_product_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('kasu_type', 120)->nullable();
            $table->string('storage_method', 120)->nullable();
            $table->timestampsTz();
        });

        Schema::create('food_product_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('food_category', 120)->nullable();
            $table->text('allergen_note')->nullable();
            $table->string('storage_method', 120)->nullable();
            $table->unsignedInteger('shelf_life_days')->nullable();
            $table->timestampsTz();
        });

        Schema::create('goods_product_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('goods_category', 120)->nullable();
            $table->string('material', 120)->nullable();
            $table->string('size_description', 120)->nullable();
            $table->timestampsTz();
        });

        Schema::create('unit_conversions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('from_unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('to_unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('factor', 18, 6);
            $table->string('rounding_method', 30)->default('none');
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();

            $table->unique(['product_id', 'from_unit_id', 'to_unit_id']);
            $table->index(['from_unit_id', 'to_unit_id']);
        });

        DB::statement(<<<'SQL'
            CREATE VIEW product_display_view AS
            SELECT
                products.id,
                products.product_code,
                products.product_type,
                products.name,
                products.display_name,
                products.brand_name,
                products.series_name,
                products.category_name,
                products.capacity_value,
                capacity_units.symbol AS capacity_unit_symbol,
                products.alcohol_percentage,
                products.is_alcohol,
                products.is_sales_available,
                products.is_inventory_managed,
                base_units.code AS base_unit_code,
                base_units.name AS base_unit_name,
                sales_units.code AS sales_unit_code,
                sales_units.name AS sales_unit_name,
                inventory_units.code AS inventory_unit_code,
                inventory_units.name AS inventory_unit_name,
                products.is_active
            FROM products
            JOIN units base_units ON base_units.id = products.base_unit_id
            LEFT JOIN units sales_units ON sales_units.id = products.sales_unit_id
            LEFT JOIN units inventory_units ON inventory_units.id = products.inventory_unit_id
            LEFT JOIN units capacity_units ON capacity_units.id = products.capacity_unit_id
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS product_display_view');
        Schema::dropIfExists('unit_conversions');
        Schema::dropIfExists('goods_product_details');
        Schema::dropIfExists('food_product_details');
        Schema::dropIfExists('kasu_product_details');
        Schema::dropIfExists('sake_product_details');
        Schema::dropIfExists('products');
        Schema::dropIfExists('units');
    }
};

