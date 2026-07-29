<?php

namespace App\Services\Tax;

use App\Models\LiquorTaxMonthlyFiling;
use App\Models\LiquorTaxMonthlyFilingLine;
use App\Models\LiquorTaxReliefSetting;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;

class CalculateMonthlyLiquorTaxAmountsService
{
    public function __construct(private readonly TaxRoundingService $roundingService) {}

    /**
     * @param  Collection<int, MonthlyLiquorTaxTransferSummary>  $summaries
     * @return array{lines:array<int,array<string,mixed>>,gross:string,relief:string,deduction:string,net_before_hundred:string,net_payable:string,taxable_kl:string,warning_count:int}
     */
    public function calculate(Collection $summaries, LiquorTaxReliefSetting $setting, CarbonImmutable $periodStart): array
    {
        if ($setting->scheme === 'new_scheme') {
            return $this->calculateNewScheme($summaries, $setting, $periodStart);
        }
        if ($setting->scheme !== 'legacy_scheme') {
            throw new DomainException('未対応の酒税軽減計算方式です。');
        }

        $fiscalYear = $periodStart->month >= 4 ? $periodStart->year : $periodStart->year - 1;
        $usedEligibleKl = bcadd((string) $setting->opening_eligible_quantity_kl, $this->confirmedEligibleKl($setting->manufacturing_site_code, $fiscalYear, $periodStart), 6);
        $limit = (string) $setting->legacy_annual_quantity_limit_kl;
        $rate = (string) $setting->legacy_reduction_rate;
        $grossTotal = '0.00';
        $reliefTotal = '0.00';
        $deductionTotal = '0.00';
        $taxableKlTotal = '0.000000';
        $warningCount = 0;
        $lines = [];

        if ($periodStart->month !== 4 && bccomp($usedEligibleKl, '0', 6) === 0 && ! $this->hasPriorConfirmedFiling($setting->manufacturing_site_code, $fiscalYear, $periodStart)) {
            $warningCount++;
        }

        foreach ($summaries as $summary) {
            $usedEligibleKlBefore = $usedEligibleKl;
            $gross = $summary->taxTreatment === 'taxable' ? $summary->grossTaxAmount : '0.00';
            $deduction = $summary->taxTreatment === 'return' ? $summary->grossTaxAmount : '0.00';
            $eligibleKl = '0.000000';
            $relief = '0.00';

            if ($summary->taxTreatment === 'taxable') {
                $taxableKlTotal = bcadd($taxableKlTotal, $summary->taxableKl, 6);
            }

            if ($summary->taxTreatment === 'taxable' && $summary->liquorTaxCategoryCode === 'seishu' && $summary->taxPerKl !== null) {
                $remaining = bcsub($limit, $usedEligibleKl, 6);
                if (bccomp($remaining, '0', 6) === 1) {
                    $eligibleKl = bccomp($summary->taxableKl, $remaining, 6) === 1 ? $remaining : $summary->taxableKl;
                    $reliefBase = bcmul($eligibleKl, $summary->taxPerKl, 8);
                    $relief = $this->roundingService->round(bcmul($reliefBase, $rate, 8), 'floor', 0).'.00';
                    $usedEligibleKl = bcadd($usedEligibleKl, $eligibleKl, 6);
                }
            }

            $net = bcsub(bcsub($gross, $relief, 2), $deduction, 2);
            $requiresReview = $summary->requiresReview;
            if ($requiresReview) {
                $warningCount++;
            }
            $grossTotal = bcadd($grossTotal, $gross, 2);
            $reliefTotal = bcadd($reliefTotal, $relief, 2);
            $deductionTotal = bcadd($deductionTotal, $deduction, 2);
            $cumulativeGrossBefore = null;
            $cumulativeGrossAfter = null;
            $calculationBasis = [
                'scheme' => 'legacy_scheme',
                'eligible_kl_before' => $usedEligibleKlBefore,
                'eligible_kl_after' => $usedEligibleKl,
                'annual_quantity_limit_kl' => $limit,
                'reduction_rate' => $rate,
                'relief_base_amount' => $summary->taxPerKl === null ? '0.00' : bcmul($eligibleKl, $summary->taxPerKl, 2),
                'rounding' => 'floor_to_yen',
            ];
            $lines[] = compact('summary', 'gross', 'eligibleKl', 'relief', 'deduction', 'net', 'requiresReview', 'cumulativeGrossBefore', 'cumulativeGrossAfter', 'calculationBasis');
        }

        $netBeforeHundred = bcsub(bcsub($grossTotal, $reliefTotal, 2), $deductionTotal, 2);
        $netPayable = bccomp($netBeforeHundred, '0', 2) === 1
            ? bcmul(bcdiv($netBeforeHundred, '100', 0), '100', 2)
            : '0.00';

        return [
            'lines' => $lines, 'gross' => $grossTotal, 'relief' => $reliefTotal,
            'deduction' => $deductionTotal, 'net_before_hundred' => $netBeforeHundred,
            'net_payable' => $netPayable, 'taxable_kl' => $taxableKlTotal,
            'warning_count' => $warningCount,
        ];
    }

    /**
     * @param  Collection<int, MonthlyLiquorTaxTransferSummary>  $summaries
     * @return array{lines:array<int,array<string,mixed>>,gross:string,relief:string,deduction:string,net_before_hundred:string,net_payable:string,taxable_kl:string,warning_count:int}
     */
    private function calculateNewScheme(Collection $summaries, LiquorTaxReliefSetting $setting, CarbonImmutable $periodStart): array
    {
        if ($setting->prior_year_peak_taxable_quantity_kl === null) {
            throw new DomainException('新制度の前年最大品目課税移出数量が設定されていません。');
        }

        $fiscalYear = $periodStart->month >= 4 ? $periodStart->year : $periodStart->year - 1;
        $summaries = $this->chronologicalNewSchemeSummaries($summaries);
        $rates = $this->newSchemeRates((string) $setting->prior_year_peak_taxable_quantity_kl);
        $cumulativeGross = bcadd((string) $setting->opening_gross_tax_amount, $this->confirmedCumulativeGross($setting->manufacturing_site_code, $fiscalYear, $periodStart), 2);
        $grossTotal = '0.00';
        $reliefTotal = '0.00';
        $deductionTotal = '0.00';
        $taxableKlTotal = '0.000000';
        $warningCount = 0;
        $lines = [];

        foreach ($summaries as $summary) {
            $cumulativeGrossBefore = $cumulativeGross;
            $gross = $summary->taxTreatment === 'taxable' ? $summary->grossTaxAmount : '0.00';
            $relief = '0.00';
            $deduction = '0.00';
            $eligibleKl = '0.000000';
            $segments = [];
            $direction = 'none';
            $reversedRelief = '0.00';

            if ($summary->taxTreatment === 'taxable') {
                $taxableKlTotal = bcadd($taxableKlTotal, $summary->taxableKl, 6);
                $eligibleKl = $summary->taxableKl;
                $after = bcadd($cumulativeGross, $summary->grossTaxAmount, 2);
                $segments = $this->reliefBreakdown($cumulativeGross, $after, $rates);
                $relief = $this->reliefDelta($cumulativeGross, $after, $rates);
                $cumulativeGross = $after;
                $direction = 'forward';
            } elseif ($summary->taxTreatment === 'return') {
                if (bccomp($summary->grossTaxAmount, $cumulativeGross, 2) === 1) {
                    $warningCount++;
                }
                $after = bcsub($cumulativeGross, $summary->grossTaxAmount, 2);
                if (bccomp($after, '0', 2) === -1) {
                    $after = '0.00';
                }
                $segments = $this->reliefBreakdown($after, $cumulativeGross, $rates);
                $reversedRelief = $this->reliefDelta($after, $cumulativeGross, $rates);
                $deduction = bcsub($summary->grossTaxAmount, $reversedRelief, 2);
                $cumulativeGross = $after;
                $direction = 'reverse';
            }

            $net = bcsub(bcsub($gross, $relief, 2), $deduction, 2);
            $requiresReview = $summary->requiresReview;
            if ($requiresReview) {
                $warningCount++;
            }
            $grossTotal = bcadd($grossTotal, $gross, 2);
            $reliefTotal = bcadd($reliefTotal, $relief, 2);
            $deductionTotal = bcadd($deductionTotal, $deduction, 2);
            $cumulativeGrossAfter = $cumulativeGross;
            $calculationBasis = [
                'scheme' => 'new_scheme',
                'direction' => $direction,
                'prior_year_peak_taxable_quantity_kl' => (string) $setting->prior_year_peak_taxable_quantity_kl,
                'rate_column' => $this->newSchemeRateColumn((string) $setting->prior_year_peak_taxable_quantity_kl),
                'segments' => $segments,
                'relief_amount' => $relief,
                'reversed_relief_amount' => $reversedRelief,
                'deduction_amount' => $deduction,
                'rounding' => 'floor_to_yen',
            ];
            $lines[] = compact('summary', 'gross', 'eligibleKl', 'relief', 'deduction', 'net', 'requiresReview', 'cumulativeGrossBefore', 'cumulativeGrossAfter', 'calculationBasis');
        }

        $netBeforeHundred = bcsub(bcsub($grossTotal, $reliefTotal, 2), $deductionTotal, 2);
        $netPayable = bccomp($netBeforeHundred, '0', 2) === 1
            ? bcmul(bcdiv($netBeforeHundred, '100', 0), '100', 2)
            : '0.00';

        return [
            'lines' => $lines, 'gross' => $grossTotal, 'relief' => $reliefTotal,
            'deduction' => $deductionTotal, 'net_before_hundred' => $netBeforeHundred,
            'net_payable' => $netPayable, 'taxable_kl' => $taxableKlTotal,
            'warning_count' => $warningCount,
        ];
    }

    /**
     * New-scheme cumulative bands must be applied in actual transfer/return order.
     *
     * @param  Collection<int, MonthlyLiquorTaxTransferSummary>  $summaries
     * @return Collection<int, MonthlyLiquorTaxTransferSummary>
     */
    private function chronologicalNewSchemeSummaries(Collection $summaries): Collection
    {
        return $summaries->flatMap(function (MonthlyLiquorTaxTransferSummary $summary): array {
            if ($summary->sources === []) {
                return [$summary];
            }

            return collect($summary->sources)->map(fn (MonthlyLiquorTaxSourceData $source): MonthlyLiquorTaxTransferSummary => new MonthlyLiquorTaxTransferSummary(
                year: $summary->year,
                month: $summary->month,
                periodStart: $summary->periodStart,
                periodEnd: $summary->periodEnd,
                liquorTaxCategoryId: $summary->liquorTaxCategoryId,
                liquorTaxCategoryCode: $summary->liquorTaxCategoryCode,
                liquorTaxCategoryName: $summary->liquorTaxCategoryName,
                liquorTaxability: $summary->liquorTaxability,
                liquorTaxRuleId: $summary->liquorTaxRuleId,
                calculationMethod: $summary->calculationMethod,
                taxPerKl: $summary->taxPerKl,
                reductionRate: $summary->reductionRate,
                taxTreatment: $source->taxTreatment,
                sourceType: $source->sourceType,
                reportingAlcoholPercentage: $source->reportingAlcoholPercentage,
                taxableKl: $source->taxableKl,
                estimatedAmount: $source->taxTreatment === 'return' ? bcmul($source->grossTaxAmount, '-1', 2) : $source->grossTaxAmount,
                grossTaxAmount: $source->grossTaxAmount,
                requiresReview: $source->requiresReview,
                shipmentCount: 1,
                lineCount: 1,
                sources: [$source],
            ))->all();
        })->sortBy(function (MonthlyLiquorTaxTransferSummary $summary): string {
            $source = $summary->sources[0] ?? null;

            return implode('|', [
                $source?->sourceDate ?? $summary->periodStart->toDateString(),
                $source?->sourceDocumentNumber ?? '',
                str_pad((string) ($source?->sourceLineId ?? 0), 12, '0', STR_PAD_LEFT),
            ]);
        })->values();
    }

    /** @return array{0:string,1:string,2:string} */
    private function newSchemeRates(string $priorYearPeakKl): array
    {
        if (bccomp($priorYearPeakKl, '400', 6) <= 0) {
            return ['0.20', '0.10', '0.05'];
        }
        if (bccomp($priorYearPeakKl, '1000', 6) <= 0) {
            return ['0.15', '0.075', '0.0375'];
        }
        if (bccomp($priorYearPeakKl, '1300', 6) <= 0) {
            return ['0.10', '0.05', '0.025'];
        }

        return ['0.05', '0.025', '0.0125'];
    }

    private function newSchemeRateColumn(string $priorYearPeakKl): string
    {
        if (bccomp($priorYearPeakKl, '400', 6) <= 0) {
            return 'A';
        }
        if (bccomp($priorYearPeakKl, '1000', 6) <= 0) {
            return 'B';
        }
        if (bccomp($priorYearPeakKl, '1300', 6) <= 0) {
            return 'C';
        }

        return 'D';
    }

    /**
     * @param  array{0:string,1:string,2:string}  $rates
     * @return array<int, array{band:string,from:string,to:?string,tax_base_amount:string,rate:string,relief_amount_unrounded:string}>
     */
    private function reliefBreakdown(string $lower, string $upper, array $rates): array
    {
        $bands = [
            ['band' => 'up_to_50m', 'from' => '0.00', 'to' => '50000000.00', 'rate' => $rates[0]],
            ['band' => '50m_to_80m', 'from' => '50000000.00', 'to' => '80000000.00', 'rate' => $rates[1]],
            ['band' => '80m_to_100m', 'from' => '80000000.00', 'to' => '100000000.00', 'rate' => $rates[2]],
            ['band' => 'over_100m', 'from' => '100000000.00', 'to' => null, 'rate' => '0'],
        ];

        return collect($bands)->map(function (array $band) use ($lower, $upper): array {
            $segmentStart = bccomp($lower, $band['from'], 2) === 1 ? $lower : $band['from'];
            $segmentEnd = $band['to'] === null || bccomp($upper, $band['to'], 2) === -1 ? $upper : $band['to'];
            $base = bccomp($segmentEnd, $segmentStart, 2) === 1 ? bcsub($segmentEnd, $segmentStart, 2) : '0.00';

            return [
                ...$band,
                'tax_base_amount' => $base,
                'relief_amount_unrounded' => bcmul($base, $band['rate'], 8),
            ];
        })->filter(fn (array $band): bool => bccomp($band['tax_base_amount'], '0', 2) === 1)->values()->all();
    }

    /** @param array{0:string,1:string,2:string} $rates */
    private function reliefDelta(string $before, string $after, array $rates): string
    {
        $delta = bcsub($this->newSchemeCumulativeRelief($after, $rates), $this->newSchemeCumulativeRelief($before, $rates), 8);

        return $this->roundingService->round($delta, 'floor', 0).'.00';
    }

    /** @param array{0:string,1:string,2:string} $rates */
    private function newSchemeCumulativeRelief(string $gross, array $rates): string
    {
        $gross = bccomp($gross, '0', 2) === 1 ? $gross : '0.00';
        $first = $this->bandAmount($gross, '0', '50000000');
        $second = $this->bandAmount($gross, '50000000', '80000000');
        $third = $this->bandAmount($gross, '80000000', '100000000');

        return bcadd(bcadd(bcmul($first, $rates[0], 8), bcmul($second, $rates[1], 8), 8), bcmul($third, $rates[2], 8), 8);
    }

    private function bandAmount(string $gross, string $start, string $end): string
    {
        if (bccomp($gross, $start, 2) <= 0) {
            return '0.00';
        }
        $capped = bccomp($gross, $end, 2) === 1 ? $end : $gross;

        return bcsub($capped, $start, 2);
    }

    private function confirmedCumulativeGross(string $siteCode, int $fiscalYear, CarbonImmutable $periodStart): string
    {
        $value = LiquorTaxMonthlyFilingLine::query()
            ->join('liquor_tax_monthly_filings as filings', 'filings.id', '=', 'liquor_tax_monthly_filing_lines.liquor_tax_monthly_filing_id')
            ->where('filings.manufacturing_site_code', $siteCode)
            ->where('filings.fiscal_year', $fiscalYear)
            ->where('filings.status', 'confirmed')
            ->whereDate('filings.period_end', '<', $periodStart->toDateString())
            ->selectRaw("COALESCE(SUM(CASE WHEN liquor_tax_monthly_filing_lines.tax_treatment = 'taxable' THEN liquor_tax_monthly_filing_lines.gross_tax_amount WHEN liquor_tax_monthly_filing_lines.tax_treatment = 'return' THEN -liquor_tax_monthly_filing_lines.gross_tax_amount ELSE 0 END), 0) AS cumulative_gross")
            ->value('cumulative_gross');

        return bcadd((string) ($value ?? '0'), '0', 2);
    }

    private function confirmedEligibleKl(string $siteCode, int $fiscalYear, CarbonImmutable $periodStart): string
    {
        return bcadd((string) LiquorTaxMonthlyFilingLine::query()
            ->join('liquor_tax_monthly_filings as filings', 'filings.id', '=', 'liquor_tax_monthly_filing_lines.liquor_tax_monthly_filing_id')
            ->where('filings.manufacturing_site_code', $siteCode)->where('filings.fiscal_year', $fiscalYear)
            ->where('filings.status', 'confirmed')->whereDate('filings.period_end', '<', $periodStart->toDateString())
            ->sum('liquor_tax_monthly_filing_lines.relief_eligible_kl'), '0', 6);
    }

    private function hasPriorConfirmedFiling(string $siteCode, int $fiscalYear, CarbonImmutable $periodStart): bool
    {
        return LiquorTaxMonthlyFiling::query()
            ->where('manufacturing_site_code', $siteCode)->where('fiscal_year', $fiscalYear)
            ->where('status', 'confirmed')->whereDate('period_end', '<', $periodStart->toDateString())->exists();
    }
}
