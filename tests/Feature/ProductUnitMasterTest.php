<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SakeProductDetail;
use App\Models\Unit;
use App\Models\UnitConversion;
use Database\Seeders\ProductUnitMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductUnitMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_unit_master_tables_and_view_exist(): void
    {
        foreach ([
            'units',
            'products',
            'sake_product_details',
            'kasu_product_details',
            'food_product_details',
            'goods_product_details',
            'unit_conversions',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table [{$table}] does not exist.");
        }

        $this->assertNotEmpty(DB::select("select to_regclass('product_display_view') as view_name")[0]->view_name);
    }

    public function test_products_table_has_required_columns_and_no_price_column(): void
    {
        foreach ([
            'product_code',
            'product_type',
            'name',
            'name_kana',
            'display_name',
            'brand_name',
            'series_name',
            'style_name',
            'category_name',
            'base_unit_id',
            'sales_unit_id',
            'inventory_unit_id',
            'capacity_value',
            'capacity_unit_id',
            'alcohol_percentage',
            'is_alcohol',
            'is_sales_available',
            'is_inventory_managed',
            'search_key',
            'legacy_code',
            'is_active',
            'disabled_at',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('products', $column), "Column [products.{$column}] does not exist.");
        }

        foreach (['price', 'unit_price', 'standard_price'] as $forbiddenColumn) {
            $this->assertFalse(Schema::hasColumn('products', $forbiddenColumn), "Forbidden price column [products.{$forbiddenColumn}] exists.");
        }
    }

    public function test_unit_seed_creates_default_units(): void
    {
        $this->seed(ProductUnitMasterSeeder::class);

        foreach (['bottle', 'case', 'liter', 'milliliter', 'kilogram', 'piece'] as $code) {
            $this->assertDatabaseHas('units', [
                'code' => $code,
                'is_active' => true,
            ]);
        }
    }

    public function test_sake_product_can_have_detail_and_unit_conversion(): void
    {
        $this->seed(ProductUnitMasterSeeder::class);

        $bottle = Unit::where('code', 'bottle')->firstOrFail();
        $case = Unit::where('code', 'case')->firstOrFail();
        $milliliter = Unit::where('code', 'milliliter')->firstOrFail();

        $product = Product::create([
            'product_code' => 'SAKE001',
            'product_type' => 'sake',
            'name' => '純米吟醸 720ml',
            'name_kana' => 'ジュンマイギンジョウ',
            'display_name' => '純米吟醸',
            'brand_name' => 'AI蔵',
            'style_name' => '火入',
            'category_name' => '清酒',
            'base_unit_id' => $bottle->id,
            'sales_unit_id' => $bottle->id,
            'inventory_unit_id' => $bottle->id,
            'capacity_value' => '720.0000',
            'capacity_unit_id' => $milliliter->id,
            'alcohol_percentage' => '15.50',
            'is_alcohol' => true,
            'search_key' => 'SAKE001 純米吟醸 720ml ジュンマイギンジョウ AI蔵',
        ]);

        SakeProductDetail::create([
            'product_id' => $product->id,
            'liquor_tax_category_code' => 'seishu',
            'liquor_type_name' => '清酒',
            'ingredients' => '米、米麹',
            'rice_polishing_ratio' => '55.00',
            'production_method' => '吟醸造り',
        ]);

        UnitConversion::create([
            'product_id' => $product->id,
            'from_unit_id' => $case->id,
            'to_unit_id' => $bottle->id,
            'factor' => '12.000000',
        ]);

        $this->assertSame('清酒', $product->sakeDetail->liquor_type_name);
        $this->assertSame('本', $product->baseUnit->name);
        $this->assertSame('12.000000', $product->unitConversions()->firstOrFail()->factor);
    }

    public function test_product_display_view_returns_joined_unit_values(): void
    {
        $this->seed(ProductUnitMasterSeeder::class);

        $bottle = Unit::where('code', 'bottle')->firstOrFail();
        $milliliter = Unit::where('code', 'milliliter')->firstOrFail();

        Product::create([
            'product_code' => 'SAKE002',
            'product_type' => 'sake',
            'name' => '本醸造 1800ml',
            'display_name' => '本醸造',
            'style_name' => '生',
            'base_unit_id' => $bottle->id,
            'sales_unit_id' => $bottle->id,
            'inventory_unit_id' => $bottle->id,
            'capacity_value' => '1800.0000',
            'capacity_unit_id' => $milliliter->id,
            'alcohol_percentage' => '15.00',
            'is_alcohol' => true,
        ]);

        $row = DB::table('product_display_view')
            ->where('product_code', 'SAKE002')
            ->first();

        $this->assertSame('本醸造', $row->display_name);
        $this->assertSame('生', $row->style_name);
        $this->assertSame('bottle', $row->base_unit_code);
        $this->assertSame('ml', $row->capacity_unit_symbol);
    }

    public function test_product_can_be_disabled_without_deleting_history_target(): void
    {
        $this->seed(ProductUnitMasterSeeder::class);

        $piece = Unit::where('code', 'piece')->firstOrFail();

        $product = Product::create([
            'product_code' => 'GOODS001',
            'product_type' => 'goods',
            'name' => 'ロゴ入りグラス',
            'display_name' => 'ロゴ入りグラス',
            'base_unit_id' => $piece->id,
            'is_alcohol' => false,
        ]);

        $product->update([
            'is_active' => false,
            'disabled_at' => now(),
        ]);

        $this->assertFalse($product->refresh()->is_active);
        $this->assertNotNull($product->disabled_at);
        $this->assertDatabaseHas('products', [
            'product_code' => 'GOODS001',
            'is_active' => false,
        ]);
    }
}

