<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Unit;
use App\Services\Tax\CalculateLiquorTaxService;
use Database\Seeders\LiquorTaxMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculateLiquorTaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_seishu_liquor_tax_from_capacity_quantity_and_tax_per_kl(): void
    {
        $this->seed([ProductUnitMasterSeeder::class, LiquorTaxMasterSeeder::class]);

        $bottle = Unit::where('code', 'bottle')->firstOrFail();
        $milliliter = Unit::where('code', 'milliliter')->firstOrFail();

        $product = Product::create([
            'product_code' => 'LIQUOR-TAX-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Liquor Tax Sake',
            'display_name' => 'Liquor Tax Sake',
            'base_unit_id' => $bottle->id,
            'capacity_value' => '720.0000',
            'capacity_unit_id' => $milliliter->id,
            'alcohol_percentage' => '15.50',
            'is_alcohol' => true,
        ]);
        $product->sakeDetail()->create([
            'liquor_tax_category_code' => 'seishu',
        ]);

        $calculated = app(CalculateLiquorTaxService::class)
            ->calculate($product->refresh()->load(['sakeDetail', 'capacityUnit']), '3.0000', '2026-05-23');

        $this->assertSame('seishu', $calculated->category->code);
        $this->assertSame('0.002160', $calculated->taxableKl);
        $this->assertSame('216.00', $calculated->estimatedAmount);
    }

    public function test_non_liquor_product_has_zero_liquor_tax(): void
    {
        $this->seed([ProductUnitMasterSeeder::class, LiquorTaxMasterSeeder::class]);

        $piece = Unit::where('code', 'piece')->firstOrFail();

        $product = Product::create([
            'product_code' => 'LIQUOR-TAX-GOODS-001',
            'product_type' => 'goods',
            'name' => 'Liquor Tax Goods',
            'display_name' => 'Liquor Tax Goods',
            'base_unit_id' => $piece->id,
            'is_alcohol' => false,
        ]);

        $calculated = app(CalculateLiquorTaxService::class)
            ->calculate($product->refresh()->load(['sakeDetail', 'capacityUnit']), '3.0000', '2026-05-23');

        $this->assertSame('non_liquor', $calculated->category->code);
        $this->assertNull($calculated->rule);
        $this->assertSame('0.000000', $calculated->taxableKl);
        $this->assertSame('0.00', $calculated->estimatedAmount);
    }

    public function test_it_calculates_liqueur_by_whole_alcohol_degree_across_the_2026_rate_change(): void
    {
        $this->seed([ProductUnitMasterSeeder::class, LiquorTaxMasterSeeder::class]);

        $bottle = Unit::where('code', 'bottle')->firstOrFail();
        $milliliter = Unit::where('code', 'milliliter')->firstOrFail();
        $product = Product::create([
            'product_code' => 'LIQUOR-TAX-LIQUEUR-001',
            'product_type' => 'sake',
            'name' => 'Liquor Tax Liqueur',
            'display_name' => 'Liquor Tax Liqueur 19.4%',
            'base_unit_id' => $bottle->id,
            'capacity_value' => '720.0000',
            'capacity_unit_id' => $milliliter->id,
            'alcohol_percentage' => '19.40',
            'is_alcohol' => true,
        ]);
        $product->sakeDetail()->create(['liquor_tax_category_code' => 'liqueur']);
        $product = $product->refresh()->load(['sakeDetail', 'capacityUnit']);

        $before = app(CalculateLiquorTaxService::class)->calculate($product, '1.0000', '2026-09-30');
        $after = app(CalculateLiquorTaxService::class)->calculate($product, '1.0000', '2026-10-01');

        $this->assertSame('liqueur_per_degree_2023_10_01', $before->rule?->code);
        $this->assertSame('liqueur_per_degree_2026_10_01', $after->rule?->code);
        $this->assertSame('190000.0000', $before->taxPerKl);
        $this->assertSame('190000.0000', $after->taxPerKl);
        $this->assertSame('136.80', $before->estimatedAmount);
        $this->assertSame('136.80', $after->estimatedAmount);
    }
}
