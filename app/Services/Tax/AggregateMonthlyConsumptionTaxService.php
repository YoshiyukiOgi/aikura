<?php

namespace App\Services\Tax;

use App\Models\InvoiceLine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AggregateMonthlyConsumptionTaxService
{
    /**
     * @return Collection<int, MonthlyConsumptionTaxSummary>
     */
    public function aggregate(int $year, int $month): Collection
    {
        $periodStart = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $periodEnd = $periodStart->endOfMonth();

        return InvoiceLine::query()
            ->join('invoice_headers', 'invoice_headers.id', '=', 'invoice_lines.invoice_header_id')
            ->whereNotIn('invoice_headers.status', ['draft', 'cancelled'])
            ->whereNull('invoice_headers.cancelled_at')
            ->whereNotNull('invoice_headers.confirmed_at')
            ->whereNotNull('invoice_lines.consumption_tax_category_id')
            ->whereBetween('invoice_headers.invoice_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->groupBy([
                'invoice_lines.consumption_tax_category_id',
                'invoice_lines.consumption_tax_category_code',
                'invoice_lines.consumption_tax_category_name',
                'invoice_lines.consumption_taxability',
                'invoice_lines.consumption_tax_rate_id',
                'invoice_lines.tax_rate',
                'invoice_lines.consumption_tax_rate_effective_from',
            ])
            ->orderBy('invoice_lines.consumption_tax_category_code')
            ->orderBy('invoice_lines.tax_rate')
            ->select([
                'invoice_lines.consumption_tax_category_id',
                'invoice_lines.consumption_tax_category_code',
                'invoice_lines.consumption_tax_category_name',
                'invoice_lines.consumption_taxability',
                'invoice_lines.consumption_tax_rate_id',
                'invoice_lines.tax_rate',
                'invoice_lines.consumption_tax_rate_effective_from',
            ])
            ->selectRaw('COALESCE(SUM(invoice_lines.amount), 0) as taxable_amount')
            ->selectRaw('COALESCE(SUM(invoice_lines.tax_amount), 0) as tax_amount')
            ->selectRaw('COALESCE(SUM(invoice_lines.total_amount), 0) as total_amount')
            ->selectRaw('COUNT(DISTINCT invoice_headers.id) as invoice_count')
            ->selectRaw('COUNT(invoice_lines.id) as line_count')
            ->get()
            ->map(function (object $row) use ($year, $month, $periodStart, $periodEnd): MonthlyConsumptionTaxSummary {
                $rateEffectiveFrom = $row->consumption_tax_rate_effective_from === null
                    ? null
                    : CarbonImmutable::parse($row->consumption_tax_rate_effective_from);

                return new MonthlyConsumptionTaxSummary(
                    year: $year,
                    month: $month,
                    periodStart: $periodStart,
                    periodEnd: $periodEnd,
                    consumptionTaxCategoryId: (int) $row->consumption_tax_category_id,
                    consumptionTaxCategoryCode: (string) $row->consumption_tax_category_code,
                    consumptionTaxCategoryName: (string) $row->consumption_tax_category_name,
                    consumptionTaxability: (string) $row->consumption_taxability,
                    consumptionTaxRateId: $row->consumption_tax_rate_id === null ? null : (int) $row->consumption_tax_rate_id,
                    taxRate: $row->tax_rate === null ? null : bcadd((string) $row->tax_rate, '0', 4),
                    rateEffectiveFrom: $rateEffectiveFrom,
                    taxableAmount: bcadd((string) $row->taxable_amount, '0', 2),
                    taxAmount: bcadd((string) $row->tax_amount, '0', 2),
                    totalAmount: bcadd((string) $row->total_amount, '0', 2),
                    invoiceCount: (int) $row->invoice_count,
                    lineCount: (int) $row->line_count,
                );
            });
    }
}
