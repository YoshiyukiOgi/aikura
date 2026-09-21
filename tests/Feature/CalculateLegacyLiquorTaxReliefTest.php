<?php

namespace Tests\Feature;

use App\Models\LiquorTaxReliefSetting;
use App\Services\Tax\CalculateMonthlyLiquorTaxAmountsService;
use App\Services\Tax\MonthlyLiquorTaxTransferSummary;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CalculateLegacyLiquorTaxReliefTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_legacy_relief_after_aggregating_e_tax_tax_base(): void
    {
        $result = app(CalculateMonthlyLiquorTaxAmountsService::class)->calculate(
            new Collection([
                $this->summary('0.030240', '3024.00', 13),
                $this->summary('0.260040', '26004.00', 14),
                $this->summary('2.307420', '230742.00', 15),
                $this->summary('0.543600', '54360.00', 16),
            ]),
            new LiquorTaxReliefSetting([
                'manufacturing_site_code' => 'main',
                'scheme' => 'legacy_scheme',
                'opening_eligible_quantity_kl' => '0.000000',
                'legacy_annual_quantity_limit_kl' => '200.000000',
                'legacy_reduction_rate' => '0.2000',
            ]),
            CarbonImmutable::parse('2026-04-01'),
        );

        $this->assertSame('314130.00', $result['gross']);
        $this->assertSame('62826.00', $result['relief']);
        $this->assertSame('251304.00', $result['net_before_hundred']);
        $this->assertSame('251300.00', $result['net_payable']);
        $this->assertSame(['604.00', '5200.00', '46148.00', '10874.00'], array_column($result['lines'], 'relief'));
        $this->assertSame('314130.00', $result['lines'][0]['calculationBasis']['e_tax_relief_base_amount']);
    }

    private function summary(string $taxableKl, string $gross, int $alcoholPercentage): MonthlyLiquorTaxTransferSummary
    {
        return new MonthlyLiquorTaxTransferSummary(
            year: 2026,
            month: 4,
            periodStart: CarbonImmutable::parse('2026-04-01'),
            periodEnd: CarbonImmutable::parse('2026-04-30'),
            liquorTaxCategoryId: 1,
            liquorTaxCategoryCode: 'seishu',
            liquorTaxCategoryName: '清酒',
            liquorTaxability: 'taxable',
            liquorTaxRuleId: 1,
            calculationMethod: 'fixed_per_kl',
            taxPerKl: '100000.0000',
            reductionRate: '0.2000',
            taxTreatment: 'taxable',
            sourceType: 'shipment',
            reportingAlcoholPercentage: $alcoholPercentage,
            taxableKl: $taxableKl,
            estimatedAmount: $gross,
            grossTaxAmount: $gross,
            requiresReview: false,
            shipmentCount: 1,
            lineCount: 1,
            sources: [],
        );
    }
}
