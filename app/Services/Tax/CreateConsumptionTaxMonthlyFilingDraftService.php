<?php

namespace App\Services\Tax;

use App\Exceptions\Tax\ConsumptionTaxMonthlyFilingDraftException;
use App\Models\ConsumptionTaxMonthlyFiling;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CreateConsumptionTaxMonthlyFilingDraftService
{
    public function __construct(
        private readonly AggregateMonthlyConsumptionTaxService $aggregateMonthlyConsumptionTaxService,
    ) {
    }

    public function create(int $year, int $month, ?string $reason = null): ConsumptionTaxMonthlyFiling
    {
        $summaries = $this->aggregateMonthlyConsumptionTaxService->aggregate($year, $month);
        $periodStart = $summaries->first()?->periodStart
            ?? CarbonImmutable::create($year, $month, 1)->startOfDay();
        $periodEnd = $summaries->first()?->periodEnd ?? $periodStart->endOfMonth();

        return DB::transaction(function () use ($year, $month, $periodStart, $periodEnd, $summaries, $reason): ConsumptionTaxMonthlyFiling {
            $filing = ConsumptionTaxMonthlyFiling::query()
                ->where('year', $year)
                ->where('month', $month)
                ->lockForUpdate()
                ->first();

            if ($filing !== null && $filing->status !== 'draft') {
                throw ConsumptionTaxMonthlyFilingDraftException::alreadyConfirmed($year, $month);
            }

            $totalTaxableAmount = '0.00';
            $totalTaxAmount = '0.00';
            $totalAmount = '0.00';
            $invoiceCount = 0;
            $lineCount = 0;

            foreach ($summaries as $summary) {
                $totalTaxableAmount = bcadd($totalTaxableAmount, $summary->taxableAmount, 2);
                $totalTaxAmount = bcadd($totalTaxAmount, $summary->taxAmount, 2);
                $totalAmount = bcadd($totalAmount, $summary->totalAmount, 2);
                $invoiceCount += $summary->invoiceCount;
                $lineCount += $summary->lineCount;
            }

            $filing = ConsumptionTaxMonthlyFiling::updateOrCreate(
                [
                    'year' => $year,
                    'month' => $month,
                ],
                [
                    'status' => 'draft',
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'total_taxable_amount' => $totalTaxableAmount,
                    'total_tax_amount' => $totalTaxAmount,
                    'total_amount' => $totalAmount,
                    'invoice_count' => $invoiceCount,
                    'line_count' => $lineCount,
                    'calculated_at' => now(),
                    'confirmed_at' => null,
                    'closed_at' => null,
                    'reason' => $reason,
                ],
            );

            $filing->lines()->delete();

            $lineNo = 1;
            foreach ($summaries as $summary) {
                $filing->lines()->create([
                    'line_no' => $lineNo,
                    'consumption_tax_category_id' => $summary->consumptionTaxCategoryId,
                    'consumption_tax_category_code' => $summary->consumptionTaxCategoryCode,
                    'consumption_tax_category_name' => $summary->consumptionTaxCategoryName,
                    'consumption_taxability' => $summary->consumptionTaxability,
                    'consumption_tax_rate_id' => $summary->consumptionTaxRateId,
                    'tax_rate' => $summary->taxRate,
                    'consumption_tax_rate_effective_from' => $summary->rateEffectiveFrom?->toDateString(),
                    'taxable_amount' => $summary->taxableAmount,
                    'tax_amount' => $summary->taxAmount,
                    'total_amount' => $summary->totalAmount,
                    'invoice_count' => $summary->invoiceCount,
                    'line_count' => $summary->lineCount,
                ]);

                $lineNo++;
            }

            return $filing->refresh()->load('lines');
        });
    }
}
