<?php

namespace Tests\Feature;

use App\Models\ConsumptionTaxCategory;
use App\Models\Product;
use App\Models\Unit;
use App\Services\Tax\ResolveProductConsumptionTaxCategoryService;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\TaxMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductConsumptionTaxCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_table_has_consumption_tax_category_column(): void
    {
        $this->assertTrue(Schema::hasColumn('products', 'consumption_tax_category_id'));
    }

    public function test_product_can_have_explicit_consumption_tax_category(): void
    {
        $this->seed([ProductUnitMasterSeeder::class, TaxMasterSeeder::class]);

        $piece = Unit::where('code', 'piece')->firstOrFail();
        $nonTaxable = ConsumptionTaxCategory::where('code', 'non_taxable')->firstOrFail();

        $product = Product::create([
            'product_code' => 'TAX-EXPLICIT-001',
            'product_type' => 'goods',
            'name' => 'Explicit Tax Product',
            'display_name' => 'Explicit Tax Product',
            'base_unit_id' => $piece->id,
            'consumption_tax_category_id' => $nonTaxable->id,
            'is_alcohol' => false,
        ]);

        $resolved = app(ResolveProductConsumptionTaxCategoryService::class)->resolve($product);

        $this->assertSame('non_taxable', $resolved->code);
        $this->assertSame('non_taxable', $product->consumptionTaxCategory->code);
    }

    public function test_it_resolves_default_tax_categories_by_product_type(): void
    {
        $this->seed([ProductUnitMasterSeeder::class, TaxMasterSeeder::class]);

        $bottle = Unit::where('code', 'bottle')->firstOrFail();
        $bag = Unit::where('code', 'bag')->firstOrFail();
        $piece = Unit::where('code', 'piece')->firstOrFail();

        $sake = Product::create([
            'product_code' => 'TAX-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Tax Sake',
            'display_name' => 'Tax Sake',
            'base_unit_id' => $bottle->id,
            'is_alcohol' => true,
        ]);

        $food = Product::create([
            'product_code' => 'TAX-FOOD-001',
            'product_type' => 'food',
            'name' => 'Tax Food',
            'display_name' => 'Tax Food',
            'base_unit_id' => $bag->id,
            'is_alcohol' => false,
        ]);

        $kasu = Product::create([
            'product_code' => 'TAX-KASU-001',
            'product_type' => 'kasu',
            'name' => 'Tax Kasu',
            'display_name' => 'Tax Kasu',
            'base_unit_id' => $bag->id,
            'is_alcohol' => false,
        ]);

        $goods = Product::create([
            'product_code' => 'TAX-GOODS-001',
            'product_type' => 'goods',
            'name' => 'Tax Goods',
            'display_name' => 'Tax Goods',
            'base_unit_id' => $piece->id,
            'is_alcohol' => false,
        ]);

        $service = app(ResolveProductConsumptionTaxCategoryService::class);

        $this->assertSame('taxable_standard', $service->resolve($sake)->code);
        $this->assertSame('taxable_reduced', $service->resolve($food)->code);
        $this->assertSame('taxable_reduced', $service->resolve($kasu)->code);
        $this->assertSame('taxable_standard', $service->resolve($goods)->code);
    }

    public function test_product_display_view_includes_consumption_tax_category_values(): void
    {
        $this->seed([ProductUnitMasterSeeder::class, TaxMasterSeeder::class]);

        $bag = Unit::where('code', 'bag')->firstOrFail();
        $reduced = ConsumptionTaxCategory::where('code', 'taxable_reduced')->firstOrFail();

        Product::create([
            'product_code' => 'TAX-VIEW-001',
            'product_type' => 'food',
            'name' => 'Tax View Food',
            'display_name' => 'Tax View Food',
            'base_unit_id' => $bag->id,
            'consumption_tax_category_id' => $reduced->id,
            'is_alcohol' => false,
        ]);

        $row = DB::table('product_display_view')
            ->where('product_code', 'TAX-VIEW-001')
            ->first();

        $this->assertSame('taxable_reduced', $row->consumption_tax_category_code);
        $this->assertTrue((bool) $row->consumption_tax_is_reduced_rate);
    }
}
