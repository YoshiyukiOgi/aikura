<?php

namespace Tests\Feature;

use App\Exceptions\Tax\TaxRateResolutionException;
use App\Models\ConsumptionTaxCategory;
use App\Services\Tax\ResolveConsumptionTaxRateService;
use Database\Seeders\TaxMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConsumptionTaxRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumption_tax_rates_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('consumption_tax_rates'));

        foreach ([
            'consumption_tax_category_id',
            'name',
            'rate',
            'effective_from',
            'effective_to',
            'description',
            'is_active',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('consumption_tax_rates', $column),
                "Column [consumption_tax_rates.{$column}] does not exist.",
            );
        }
    }

    public function test_tax_master_seed_creates_standard_and_reduced_tax_rates(): void
    {
        $this->seed(TaxMasterSeeder::class);

        $standard = ConsumptionTaxCategory::where('code', 'taxable_standard')->firstOrFail();
        $reduced = ConsumptionTaxCategory::where('code', 'taxable_reduced')->firstOrFail();

        $this->assertDatabaseHas('consumption_tax_rates', [
            'consumption_tax_category_id' => $standard->id,
            'rate' => '0.1000',
            'effective_from' => '2019-10-01',
            'effective_to' => null,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('consumption_tax_rates', [
            'consumption_tax_category_id' => $reduced->id,
            'rate' => '0.0800',
            'effective_from' => '2019-10-01',
            'effective_to' => null,
            'is_active' => true,
        ]);
    }

    public function test_it_resolves_standard_tax_rate_by_effective_date(): void
    {
        $this->seed(TaxMasterSeeder::class);

        $service = app(ResolveConsumptionTaxRateService::class);

        $oldRate = $service->resolve('taxable_standard', '2019-09-30');
        $currentRate = $service->resolve('taxable_standard', '2026-05-23');

        $this->assertSame('0.0800', $oldRate->rate->rate);
        $this->assertSame('0.1000', $currentRate->rate->rate);
    }

    public function test_it_resolves_reduced_tax_rate_only_after_reduced_rate_started(): void
    {
        $this->seed(TaxMasterSeeder::class);

        $service = app(ResolveConsumptionTaxRateService::class);

        $currentRate = $service->resolve('taxable_reduced', '2026-05-23');

        $this->assertSame('0.0800', $currentRate->rate->rate);

        $this->expectException(TaxRateResolutionException::class);

        $service->resolve('taxable_reduced', '2019-09-30');
    }

    public function test_it_rejects_rate_resolution_for_non_taxable_category(): void
    {
        $this->seed(TaxMasterSeeder::class);

        $this->expectException(TaxRateResolutionException::class);

        app(ResolveConsumptionTaxRateService::class)->resolve('non_taxable', '2026-05-23');
    }
}
