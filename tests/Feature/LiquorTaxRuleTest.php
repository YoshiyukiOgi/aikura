<?php

namespace Tests\Feature;

use App\Exceptions\Tax\LiquorTaxRuleResolutionException;
use App\Models\LiquorTaxCategory;
use App\Models\LiquorTaxRule;
use App\Services\Tax\ResolveLiquorTaxRuleService;
use Database\Seeders\LiquorTaxMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LiquorTaxRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_liquor_tax_rules_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('liquor_tax_rules'));

        foreach ([
            'liquor_tax_category_id',
            'code',
            'name',
            'calculation_method',
            'tax_per_kl',
            'alcohol_percentage_min',
            'alcohol_percentage_max',
            'base_alcohol_percentage',
            'additional_tax_per_kl_per_percent',
            'reduction_rate',
            'special_provision_code',
            'effective_from',
            'effective_to',
            'is_active',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('liquor_tax_rules', $column),
                "Column [liquor_tax_rules.{$column}] does not exist.",
            );
        }
    }

    public function test_liquor_tax_master_seed_creates_current_seishu_tax_rule(): void
    {
        $this->seed(LiquorTaxMasterSeeder::class);

        $seishu = LiquorTaxCategory::where('code', 'seishu')->firstOrFail();

        $this->assertDatabaseHas('liquor_tax_rules', [
            'liquor_tax_category_id' => $seishu->id,
            'code' => 'seishu_fixed_per_kl_2023_10_01',
            'calculation_method' => 'fixed_per_kl',
            'tax_per_kl' => '100000.0000',
            'effective_from' => '2023-10-01',
            'effective_to' => '2026-09-30',
            'is_active' => true,
        ]);
    }

    public function test_it_resolves_liquor_tax_rule_by_category_date_and_alcohol_range(): void
    {
        $this->seed(LiquorTaxMasterSeeder::class);

        $resolved = app(ResolveLiquorTaxRuleService::class)
            ->resolve('seishu', '2026-05-23', '15.50');

        $this->assertSame('seishu', $resolved->category->code);
        $this->assertSame('fixed_per_kl', $resolved->rule->calculation_method);
        $this->assertSame('100000.0000', $resolved->rule->tax_per_kl);
        $this->assertSame('0.0000', $resolved->rule->reduction_rate);
    }

    public function test_it_prefers_matching_alcohol_range_rule_when_present(): void
    {
        $this->seed(LiquorTaxMasterSeeder::class);

        $seishu = LiquorTaxCategory::where('code', 'seishu')->firstOrFail();

        LiquorTaxRule::create([
            'liquor_tax_category_id' => $seishu->id,
            'code' => 'seishu_test_low_alcohol',
            'name' => 'Test low alcohol seishu',
            'calculation_method' => 'fixed_per_kl',
            'tax_per_kl' => '80000.0000',
            'alcohol_percentage_min' => '0.00',
            'alcohol_percentage_max' => '10.00',
            'reduction_rate' => '0.1000',
            'effective_from' => '2023-10-01',
            'effective_to' => '2026-09-30',
            'is_active' => true,
        ]);

        $resolved = app(ResolveLiquorTaxRuleService::class)
            ->resolve('seishu', '2026-05-23', '8.00');

        $this->assertSame('seishu_test_low_alcohol', $resolved->rule->code);
        $this->assertSame('80000.0000', $resolved->rule->tax_per_kl);
        $this->assertSame('0.1000', $resolved->rule->reduction_rate);
    }

    public function test_it_rejects_non_taxable_category_and_missing_period(): void
    {
        $this->seed(LiquorTaxMasterSeeder::class);

        $service = app(ResolveLiquorTaxRuleService::class);

        $this->expectException(LiquorTaxRuleResolutionException::class);
        $service->resolve('non_liquor', '2026-05-23');
    }

    public function test_it_rejects_missing_rule_for_date_outside_effective_period(): void
    {
        $this->seed(LiquorTaxMasterSeeder::class);

        $this->expectException(LiquorTaxRuleResolutionException::class);

        app(ResolveLiquorTaxRuleService::class)->resolve('seishu', '2023-09-30', '15.50');
    }
}
