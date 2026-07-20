<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS product_display_view');

        Schema::table('products', function (Blueprint $table): void {
            $table->string('style_name', 120)->nullable()->after('series_name');

            $table->index('style_name');
        });

        $this->createProductDisplayView();
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS product_display_view');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['style_name']);
            $table->dropColumn('style_name');
        });

        $this->createProductDisplayView();
    }

    private function createProductDisplayView(): void
    {
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
                products.style_name,
                products.category_name,
                products.consumption_tax_category_id,
                consumption_tax_categories.code AS consumption_tax_category_code,
                consumption_tax_categories.name AS consumption_tax_category_name,
                consumption_tax_categories.taxability AS consumption_taxability,
                consumption_tax_categories.is_reduced_rate AS consumption_tax_is_reduced_rate,
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
            LEFT JOIN consumption_tax_categories ON consumption_tax_categories.id = products.consumption_tax_category_id
        SQL);
    }
};
