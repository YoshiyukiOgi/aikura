<?php

namespace App\Services\Tax;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AggregateMonthlyLiquorTaxTransfersService
{
    public function __construct(private readonly TaxRoundingService $roundingService) {}

    /** @return Collection<int, MonthlyLiquorTaxTransferSummary> */
    public function aggregate(int $year, int $month): Collection
    {
        $periodStart = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $periodEnd = $periodStart->endOfMonth();
        $records = collect();

        $legacyLines = DB::table('shipment_lines as lines')
            ->join('shipment_headers as headers', 'headers.id', '=', 'lines.shipment_header_id')
            ->leftJoin('shipment_liquor_tax_evidences as evidences', 'evidences.shipment_header_id', '=', 'headers.id')
            ->whereNotIn('headers.status', ['draft', 'cancelled'])->whereNull('headers.cancelled_at')
            ->whereNotNull('lines.confirmed_at')->whereNotNull('lines.confirmed_liquor_tax_category_id')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('shipment_lot_allocations as allocations')
                    ->whereColumn('allocations.shipment_line_id', 'lines.id')
                    ->whereNotNull('allocations.liquor_tax_category_id');
            })
            ->whereBetween(DB::raw('COALESCE(headers.liquor_tax_transfer_date, headers.document_date)'), [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->select([
                'headers.id as header_id', 'headers.document_number', 'headers.confirmed_liquor_tax_treatment',
                'headers.confirmed_requires_tax_review', 'lines.id as line_id', 'lines.quantity',
                'headers.confirmed_requires_evidence', 'evidences.status as evidence_status',
                'evidences.evidence_reference',
                'lines.confirmed_liquor_tax_category_id as category_id',
                'lines.confirmed_liquor_tax_category_code as category_code',
                'lines.confirmed_liquor_tax_category_name as category_name',
                'lines.confirmed_liquor_taxability as taxability',
                'lines.confirmed_liquor_tax_rule_id as rule_id',
                'lines.confirmed_liquor_tax_calculation_method as calculation_method',
                'lines.confirmed_liquor_tax_per_kl as tax_per_kl',
                'lines.confirmed_liquor_tax_reduction_rate as reduction_rate',
                'lines.confirmed_liquor_taxable_kl as taxable_kl',
            ])
            ->selectRaw('COALESCE(headers.liquor_tax_transfer_date, headers.document_date) as source_date')
            ->get();

        foreach ($legacyLines as $row) {
            $treatment = $this->treatment((string) ($row->confirmed_liquor_tax_treatment ?? ''), (string) $row->taxability);
            $records->push($this->record(
                $row, $treatment, 'shipment', (int) $row->header_id, (int) $row->line_id,
                (string) $row->quantity, (string) ($row->taxable_kl ?? '0'),
                $this->shipmentRequiresReview($row),
                $this->shipmentReviewReason($row),
            ));
        }

        $lotLines = DB::table('shipment_lot_allocations as allocations')
            ->join('shipment_lines as lines', 'lines.id', '=', 'allocations.shipment_line_id')
            ->join('shipment_headers as headers', 'headers.id', '=', 'allocations.shipment_header_id')
            ->leftJoin('shipment_liquor_tax_evidences as evidences', 'evidences.shipment_header_id', '=', 'headers.id')
            ->join('liquor_tax_categories as categories', 'categories.id', '=', 'allocations.liquor_tax_category_id')
            ->leftJoin('liquor_tax_rules as rules', 'rules.id', '=', 'allocations.liquor_tax_rule_id')
            ->where('allocations.status', 'confirmed')->whereNull('allocations.cancelled_at')
            ->whereNotIn('headers.status', ['draft', 'cancelled'])->whereNull('headers.cancelled_at')
            ->whereBetween(DB::raw('COALESCE(headers.liquor_tax_transfer_date, headers.document_date)'), [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->select([
                'headers.id as header_id', 'headers.document_number', 'headers.confirmed_liquor_tax_treatment',
                'headers.confirmed_requires_tax_review', 'allocations.id as line_id', 'allocations.quantity',
                'headers.confirmed_requires_evidence', 'evidences.status as evidence_status',
                'evidences.evidence_reference',
                'categories.id as category_id', 'categories.code as category_code', 'categories.name as category_name',
                'categories.taxability', 'rules.id as rule_id', 'rules.calculation_method',
                'allocations.liquor_tax_per_kl as tax_per_kl', 'rules.reduction_rate',
                'allocations.liquor_taxable_kl as taxable_kl',
            ])
            ->selectRaw('COALESCE(headers.liquor_tax_transfer_date, headers.document_date) as source_date')
            ->get();

        foreach ($lotLines as $row) {
            $treatment = $this->treatment((string) ($row->confirmed_liquor_tax_treatment ?? ''), (string) $row->taxability);
            $records->push($this->record(
                $row, $treatment, 'shipment_lot', (int) $row->header_id, (int) $row->line_id,
                (string) $row->quantity, (string) ($row->taxable_kl ?? '0'),
                $this->shipmentRequiresReview($row),
                $this->shipmentReviewReason($row),
            ));
        }

        $returnLines = DB::table('sales_return_lines as returns')
            ->join('sales_return_headers as headers', 'headers.id', '=', 'returns.sales_return_header_id')
            ->join('shipment_lines as source', 'source.id', '=', 'returns.source_shipment_line_id')
            ->join('shipment_headers as source_headers', 'source_headers.id', '=', 'returns.source_shipment_header_id')
            ->whereNotIn('headers.status', ['draft', 'cancelled'])->whereNull('headers.cancelled_at')
            ->whereNotNull('headers.credited_at')
            ->whereBetween('headers.return_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->select([
                'headers.id as header_id', 'headers.return_number as document_number', 'headers.return_date as source_date',
                'returns.id as line_id', 'returns.quantity', 'returns.liquor_tax_return_treatment',
                'returns.liquor_tax_return_reason', 'source_headers.confirmed_liquor_tax_treatment as source_tax_treatment',
                'source.confirmed_quantity as source_quantity',
                'source.confirmed_liquor_tax_category_id as category_id',
                'source.confirmed_liquor_tax_category_code as category_code',
                'source.confirmed_liquor_tax_category_name as category_name',
                'source.confirmed_liquor_taxability as taxability', 'source.confirmed_liquor_tax_rule_id as rule_id',
                'source.confirmed_liquor_tax_calculation_method as calculation_method',
                'source.confirmed_liquor_tax_per_kl as tax_per_kl', 'source.confirmed_liquor_tax_reduction_rate as reduction_rate',
                'source.confirmed_liquor_taxable_kl as source_taxable_kl',
            ])->get();

        foreach ($returnLines as $row) {
            $sourceQuantity = (string) ($row->source_quantity ?? '0');
            $eligibility = (string) ($row->liquor_tax_return_treatment ?? 'review');
            $review = $eligibility === 'review'
                || bccomp($sourceQuantity, '0', 4) === 0
                || $row->category_id === null
                || $row->source_tax_treatment !== 'taxable';
            $treatment = $eligibility === 'not_eligible' ? 'not_applicable' : ($review ? 'review' : 'return');
            $taxableKl = $treatment === 'return'
                ? bcmul(bcmul(bcdiv((string) ($row->source_taxable_kl ?? '0'), $sourceQuantity, 12), (string) $row->quantity, 6), '-1', 6)
                : '0.000000';
            $reviewReason = $review ? match (true) {
                $eligibility === 'review' => '返品の酒税戻入控除判定が未確認です。',
                $row->source_tax_treatment !== 'taxable' => '元出荷が課税移出ではありません。',
                default => '返品元の酒税数量を解決できません。',
            } : null;
            $records->push($this->record(
                $row, $treatment, 'sales_return', (int) $row->header_id, (int) $row->line_id,
                bcmul((string) $row->quantity, '-1', 4), $taxableKl, $review, $reviewReason,
            ));
        }

        $nonSalesLines = DB::table('non_sales_stock_operation_lines as lines')
            ->join('non_sales_stock_operation_headers as headers', 'headers.id', '=', 'lines.non_sales_stock_operation_header_id')
            ->leftJoin('liquor_tax_categories as categories', 'categories.id', '=', 'lines.liquor_tax_category_id')
            ->leftJoin('liquor_tax_rules as rules', 'rules.id', '=', 'lines.liquor_tax_rule_id')
            ->where('headers.status', 'confirmed')->whereNull('headers.cancelled_at')
            ->whereIn('headers.liquor_tax_treatment', ['taxable', 'review'])
            ->whereBetween('headers.operation_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->select([
                'headers.id as header_id', 'headers.operation_number as document_number', 'headers.operation_date as source_date',
                'headers.liquor_tax_treatment', 'headers.requires_tax_review', 'lines.id as line_id', 'lines.quantity',
                'categories.id as category_id', 'categories.code as category_code', 'categories.name as category_name',
                'categories.taxability', 'rules.id as rule_id', 'rules.calculation_method',
                'lines.liquor_tax_per_kl as tax_per_kl', 'lines.liquor_tax_reduction_rate as reduction_rate',
                'lines.liquor_taxable_kl as taxable_kl',
            ])->get();

        foreach ($nonSalesLines as $row) {
            $review = (bool) $row->requires_tax_review || $row->category_id === null;
            $records->push($this->record(
                $row, (string) $row->liquor_tax_treatment, 'non_sales', (int) $row->header_id, (int) $row->line_id,
                ltrim((string) $row->quantity, '-'), (string) ($row->taxable_kl ?? '0'), $review,
                $row->category_id === null ? '販売外出庫の商品・酒税区分が未設定です。' : null,
            ));
        }

        return $records
            ->groupBy(fn (array $r): string => implode('|', [
                $r['category_id'], $r['rule_id'] ?? 0, $r['tax_per_kl'] ?? '', $r['tax_treatment'], $r['source_type'],
            ]))
            ->map(function (Collection $group) use ($year, $month, $periodStart, $periodEnd): MonthlyLiquorTaxTransferSummary {
                $first = $group->first();
                $taxableKl = $group->reduce(fn (string $sum, array $r): string => bcadd($sum, $r['taxable_kl'], 6), '0.000000');
                $gross = '0.00';
                if (in_array($first['tax_treatment'], ['taxable', 'return'], true) && $first['tax_per_kl'] !== null) {
                    $base = bcmul(ltrim($taxableKl, '-'), $first['tax_per_kl'], 8);
                    $gross = $this->roundingService->round($base, 'floor', 0).'.00';
                }
                $estimated = $first['tax_treatment'] === 'return' ? bcmul($gross, '-1', 2) : $gross;

                return new MonthlyLiquorTaxTransferSummary(
                    year: $year, month: $month, periodStart: $periodStart, periodEnd: $periodEnd,
                    liquorTaxCategoryId: (int) $first['category_id'], liquorTaxCategoryCode: $first['category_code'],
                    liquorTaxCategoryName: $first['category_name'], liquorTaxability: $first['taxability'],
                    liquorTaxRuleId: $first['rule_id'], calculationMethod: $first['calculation_method'],
                    taxPerKl: $first['tax_per_kl'], reductionRate: $first['reduction_rate'],
                    taxTreatment: $first['tax_treatment'], sourceType: $first['source_type'], taxableKl: $taxableKl,
                    estimatedAmount: $estimated, grossTaxAmount: $gross,
                    requiresReview: $group->contains(fn (array $r): bool => $r['source']->requiresReview),
                    shipmentCount: $group->pluck('source.sourceHeaderId')->unique()->count(), lineCount: $group->count(),
                    sources: $group->pluck('source')->all(),
                );
            })->sortBy(fn (MonthlyLiquorTaxTransferSummary $s): string => implode('|', [$s->liquorTaxCategoryCode, $s->taxTreatment, $s->sourceType]))->values();
    }

    /** @return array<string, mixed> */
    private function record(object $row, string $treatment, string $sourceType, int $headerId, int $lineId, string $quantity, string $taxableKl, bool $review, ?string $reviewReason = null): array
    {
        if ($row->category_id === null) {
            $fallback = DB::table('liquor_tax_categories')->where('code', 'unresolved_liquor')->first();
            $row->category_id = $fallback?->id;
            $row->category_code = $fallback?->code;
            $row->category_name = $fallback?->name;
            $row->taxability = $fallback?->taxability;
        }
        $taxPerKl = $row->tax_per_kl === null ? null : bcadd((string) $row->tax_per_kl, '0', 4);
        $sourceGross = $taxPerKl === null ? '0.00' : $this->roundingService->round(bcmul(ltrim($taxableKl, '-'), $taxPerKl, 8), 'floor', 0).'.00';
        $source = new MonthlyLiquorTaxSourceData(
            sourceType: $sourceType, sourceHeaderId: $headerId, sourceLineId: $lineId,
            sourceDocumentNumber: $row->document_number, sourceDate: CarbonImmutable::parse($row->source_date)->toDateString(),
            taxTreatment: $treatment, quantity: bcadd($quantity, '0', 4), taxableKl: bcadd($taxableKl, '0', 6),
            grossTaxAmount: $sourceGross, requiresReview: $review, reviewReason: $reviewReason,
            evidenceStatus: $row->evidence_status ?? null,
            evidenceReference: $row->evidence_reference ?? null,
        );

        return [
            'category_id' => (int) ($row->category_id ?? 0), 'category_code' => (string) ($row->category_code ?? 'unresolved'),
            'category_name' => (string) ($row->category_name ?? '未解決'), 'taxability' => (string) ($row->taxability ?? 'unknown'),
            'rule_id' => $row->rule_id === null ? null : (int) $row->rule_id,
            'calculation_method' => $row->calculation_method, 'tax_per_kl' => $taxPerKl,
            'reduction_rate' => $row->reduction_rate === null ? null : bcadd((string) $row->reduction_rate, '0', 4),
            'tax_treatment' => $treatment, 'source_type' => $sourceType, 'taxable_kl' => bcadd($taxableKl, '0', 6),
            'source' => $source,
        ];
    }

    private function treatment(string $snapshot, string $taxability): string
    {
        if ($snapshot !== '') {
            return $snapshot;
        }

        return match ($taxability) {
            'taxable' => 'taxable', 'untaxed' => 'untaxed_transfer', 'exempt' => 'export_exempt', default => 'not_applicable',
        };
    }

    private function shipmentRequiresReview(object $row): bool
    {
        if ((bool) $row->confirmed_requires_evidence) {
            return $row->evidence_status !== 'confirmed';
        }

        return (bool) $row->confirmed_requires_tax_review;
    }

    private function shipmentReviewReason(object $row): ?string
    {
        if ((bool) $row->confirmed_requires_evidence && $row->evidence_status !== 'confirmed') {
            return '輸出免税・未納税移出の証明確認が完了していません。';
        }

        return (bool) $row->confirmed_requires_tax_review ? '出荷の税務区分を確認してください。' : null;
    }
}
