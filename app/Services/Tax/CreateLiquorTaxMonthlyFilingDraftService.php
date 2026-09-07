<?php

namespace App\Services\Tax;

use App\Exceptions\Tax\LiquorTaxMonthlyFilingDraftException;
use App\Models\LiquorTaxMonthlyFiling;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CreateLiquorTaxMonthlyFilingDraftService
{
    public function __construct(
        private readonly AggregateMonthlyLiquorTaxTransfersService $aggregateMonthlyLiquorTaxTransfersService,
        private readonly ResolveLiquorTaxReliefSettingService $resolveReliefSettingService,
        private readonly CalculateMonthlyLiquorTaxAmountsService $calculateAmountsService,
        private readonly RecalculateLiquorTaxFilingAdjustmentsService $recalculateAdjustmentsService,
    ) {}

    public function create(int $year, int $month, ?string $reason = null): LiquorTaxMonthlyFiling
    {
        $summaries = $this->aggregateMonthlyLiquorTaxTransfersService->aggregate($year, $month);
        $periodStart = $summaries->first()?->periodStart
            ?? CarbonImmutable::create($year, $month, 1)->startOfDay();
        $periodEnd = $summaries->first()?->periodEnd ?? $periodStart->endOfMonth();
        $siteCode = 'main';
        $reliefSetting = $this->resolveReliefSettingService->resolve($siteCode, $periodEnd->toDateString());
        $calculation = $this->calculateAmountsService->calculate($summaries, $reliefSetting, $periodStart);
        $fiscalYear = $periodStart->month >= 4 ? $periodStart->year : $periodStart->year - 1;

        return DB::transaction(function () use ($year, $month, $periodStart, $periodEnd, $reason, $siteCode, $reliefSetting, $calculation, $fiscalYear): LiquorTaxMonthlyFiling {
            $filing = LiquorTaxMonthlyFiling::query()
                ->where('year', $year)
                ->where('month', $month)
                ->lockForUpdate()
                ->first();

            if ($filing !== null && $filing->status !== 'draft') {
                throw LiquorTaxMonthlyFilingDraftException::alreadyConfirmed($year, $month);
            }

            $sources = collect($calculation['lines'])->flatMap(fn (array $line): array => $line['summary']->sources);
            $shipmentCount = $sources->filter(fn (MonthlyLiquorTaxSourceData $source): bool => str_starts_with($source->sourceType, 'shipment'))
                ->map(fn (MonthlyLiquorTaxSourceData $source): string => $source->sourceType.':'.$source->sourceHeaderId)->unique()->count();
            $lineCount = $sources->count();

            $filing = LiquorTaxMonthlyFiling::updateOrCreate(
                [
                    'year' => $year,
                    'month' => $month,
                ],
                [
                    'status' => 'draft',
                    'manufacturing_site_code' => $siteCode,
                    'fiscal_year' => $fiscalYear,
                    'liquor_tax_relief_setting_id' => $reliefSetting->id,
                    'relief_scheme' => $reliefSetting->scheme,
                    'calculation_rule_version' => $reliefSetting->calculation_rule_version,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'total_taxable_kl' => $calculation['taxable_kl'],
                    'total_gross_tax_amount' => $calculation['gross'],
                    'total_relief_amount' => $calculation['relief'],
                    'total_deduction_amount' => $calculation['deduction'],
                    'net_payable_amount' => $calculation['net_payable'],
                    'total_estimated_amount' => $calculation['net_payable'],
                    'warning_count' => $calculation['warning_count'],
                    'shipment_count' => $shipmentCount,
                    'line_count' => $lineCount,
                    'calculated_at' => now(),
                    'confirmed_at' => null,
                    'closed_at' => null,
                    'reason' => $reason,
                ],
            );

            $filing->lines()->delete();

            $lineNo = 1;
            foreach ($calculation['lines'] as $calculatedLine) {
                /** @var MonthlyLiquorTaxTransferSummary $summary */
                $summary = $calculatedLine['summary'];
                $line = $filing->lines()->create([
                    'line_no' => $lineNo,
                    'liquor_tax_category_id' => $summary->liquorTaxCategoryId,
                    'liquor_tax_category_code' => $summary->liquorTaxCategoryCode,
                    'liquor_tax_category_name' => $summary->liquorTaxCategoryName,
                    'liquor_taxability' => $summary->liquorTaxability,
                    'liquor_tax_rule_id' => $summary->liquorTaxRuleId,
                    'tax_treatment' => $summary->taxTreatment,
                    'source_type' => $summary->sourceType,
                    'reporting_alcohol_percentage' => $summary->reportingAlcoholPercentage,
                    'calculation_method' => $summary->calculationMethod,
                    'tax_per_kl' => $summary->taxPerKl,
                    'reduction_rate' => bccomp($calculatedLine['eligibleKl'], '0', 6) === 1 ? $reliefSetting->legacy_reduction_rate : '0.0000',
                    'taxable_kl' => $summary->taxableKl,
                    'gross_tax_amount' => $calculatedLine['gross'],
                    'relief_eligible_kl' => $calculatedLine['eligibleKl'],
                    'relief_amount' => $calculatedLine['relief'],
                    'deduction_amount' => $calculatedLine['deduction'],
                    'net_tax_amount' => $calculatedLine['net'],
                    'cumulative_gross_before' => $calculatedLine['cumulativeGrossBefore'],
                    'cumulative_gross_after' => $calculatedLine['cumulativeGrossAfter'],
                    'relief_calculation_basis' => $calculatedLine['calculationBasis'],
                    'estimated_amount' => $calculatedLine['net'],
                    'requires_review' => $calculatedLine['requiresReview'],
                    'shipment_count' => $summary->shipmentCount,
                    'line_count' => $summary->lineCount,
                ]);

                foreach ($summary->sources as $source) {
                    $filing->sources()->create([
                        'liquor_tax_monthly_filing_line_id' => $line->id,
                        'source_type' => $source->sourceType,
                        'source_header_id' => $source->sourceHeaderId,
                        'source_line_id' => $source->sourceLineId,
                        'source_document_number' => $source->sourceDocumentNumber,
                        'source_date' => $source->sourceDate,
                        'tax_treatment' => $source->taxTreatment,
                        'reporting_alcohol_percentage' => $source->reportingAlcoholPercentage,
                        'quantity' => $source->quantity,
                        'taxable_kl' => $source->taxableKl,
                        'gross_tax_amount' => $source->grossTaxAmount,
                        'requires_review' => $source->requiresReview,
                        'review_reason' => $source->reviewReason,
                        'evidence_status' => $source->evidenceStatus,
                        'evidence_reference' => $source->evidenceReference,
                        'shipment_liquor_tax_evidence_id' => $source->shipmentLiquorTaxEvidenceId,
                        'evidence_document_file_name' => $source->evidenceDocumentFileName,
                        'evidence_document_mime_type' => $source->evidenceDocumentMimeType,
                    ]);
                }

                $lineNo++;
            }

            $this->recalculateAdjustmentsService->recalculate($filing);

            return $filing->refresh()->load(['lines', 'sources', 'adjustments.approvalRequest']);
        });
    }
}
