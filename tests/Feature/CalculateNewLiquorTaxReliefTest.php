<?php

namespace Tests\Feature;

use App\Models\LiquorTaxReliefSetting;
use App\Services\Tax\CalculateMonthlyLiquorTaxAmountsService;
use App\Services\Tax\MonthlyLiquorTaxSourceData;
use App\Services\Tax\MonthlyLiquorTaxTransferSummary;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CalculateNewLiquorTaxReliefTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_applies_the_first_band_rate(): void
    {
        $result = $this->calculate('0.00', [$this->summary('taxable', '1000.00')]);

        $this->assertSame('200.00', $result['relief']);
        $this->assertSame('800.00', $result['net_before_hundred']);
    }

    public function test_it_splits_relief_when_gross_tax_crosses_fifty_million_yen(): void
    {
        $result = $this->calculate('49960000.00', [$this->summary('taxable', '100000.00')]);

        $this->assertSame('14000.00', $result['relief']);
        $this->assertSame('86000.00', $result['net_before_hundred']);
        $line = $result['lines'][0];
        $this->assertSame('49960000.00', $line['cumulativeGrossBefore']);
        $this->assertSame('50060000.00', $line['cumulativeGrossAfter']);
        $this->assertSame('A', $line['calculationBasis']['rate_column']);
        $this->assertSame(['up_to_50m', '50m_to_80m'], array_column($line['calculationBasis']['segments'], 'band'));
        $this->assertSame(['40000.00', '60000.00'], array_column($line['calculationBasis']['segments'], 'tax_base_amount'));
    }

    public function test_return_deduction_reverses_the_current_cumulative_bands(): void
    {
        $result = $this->calculate('50060000.00', [$this->summary('return', '100000.00')]);

        $this->assertSame('86000.00', $result['deduction']);
        $this->assertSame('-86000.00', $result['net_before_hundred']);
        $this->assertSame('reverse', $result['lines'][0]['calculationBasis']['direction']);
        $this->assertSame('14000.00', $result['lines'][0]['calculationBasis']['reversed_relief_amount']);
    }

    public function test_it_calculates_shipments_and_returns_in_source_date_order(): void
    {
        $result = $this->calculate('49960000.00', [
            $this->summary('return', '100000.00', '2027-04-02', 'R-001'),
            $this->summary('taxable', '100000.00', '2027-04-01', 'S-001'),
        ]);

        $this->assertSame(['taxable', 'return'], array_map(fn (array $line): string => $line['summary']->taxTreatment, $result['lines']));
        $this->assertSame('14000.00', $result['relief']);
        $this->assertSame('86000.00', $result['deduction']);
    }

    /** @param array<int, MonthlyLiquorTaxTransferSummary> $summaries */
    private function calculate(string $openingGross, array $summaries): array
    {
        $setting = new LiquorTaxReliefSetting([
            'manufacturing_site_code' => 'main',
            'scheme' => 'new_scheme',
            'opening_gross_tax_amount' => $openingGross,
            'prior_year_peak_taxable_quantity_kl' => '400.000000',
        ]);

        return app(CalculateMonthlyLiquorTaxAmountsService::class)->calculate(
            new Collection($summaries),
            $setting,
            CarbonImmutable::parse('2027-04-01'),
        );
    }

    private function summary(string $treatment, string $gross, ?string $sourceDate = null, string $documentNumber = 'DOC-001'): MonthlyLiquorTaxTransferSummary
    {
        $sources = $sourceDate === null ? [] : [new MonthlyLiquorTaxSourceData(
            sourceType: $treatment === 'return' ? 'sales_return' : 'shipment',
            sourceHeaderId: 1,
            sourceLineId: $treatment === 'return' ? 2 : 1,
            sourceDocumentNumber: $documentNumber,
            sourceDate: $sourceDate,
            taxTreatment: $treatment,
            reportingAlcoholPercentage: 15,
            quantity: $treatment === 'return' ? '-1.0000' : '1.0000',
            taxableKl: $treatment === 'return' ? '-1.000000' : '1.000000',
            grossTaxAmount: $gross,
        )];

        return new MonthlyLiquorTaxTransferSummary(
            year: 2027,
            month: 4,
            periodStart: CarbonImmutable::parse('2027-04-01'),
            periodEnd: CarbonImmutable::parse('2027-04-30'),
            liquorTaxCategoryId: 1,
            liquorTaxCategoryCode: 'seishu',
            liquorTaxCategoryName: '清酒',
            liquorTaxability: 'taxable',
            liquorTaxRuleId: 1,
            calculationMethod: 'per_kl',
            taxPerKl: '100000.0000',
            reductionRate: null,
            taxTreatment: $treatment,
            sourceType: $treatment === 'return' ? 'sales_return' : 'shipment',
            reportingAlcoholPercentage: 15,
            taxableKl: '1.000000',
            estimatedAmount: $gross,
            grossTaxAmount: $gross,
            requiresReview: false,
            shipmentCount: 1,
            lineCount: 1,
            sources: $sources,
        );
    }
}
