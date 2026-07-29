<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ShipmentActionReasonRequest;
use App\Http\Requests\Api\V1\StoreLiquorTaxAdjustmentRequest;
use App\Http\Requests\Api\V1\StoreLiquorTaxReliefSettingRequest;
use App\Http\Requests\Api\V1\StoreShipmentLiquorTaxEvidenceRequest;
use App\Http\Requests\Api\V1\StoreTaxMonthlyFilingRequest;
use App\Http\Requests\Api\V1\UpdateLiquorTaxAdjustmentSettingRequest;
use App\Models\ConsumptionTaxMonthlyFiling;
use App\Models\ConsumptionTaxMonthlyFilingLine;
use App\Models\LiquorTaxAdjustmentSetting;
use App\Models\LiquorTaxMonthlyFiling;
use App\Models\LiquorTaxMonthlyFilingAdjustment;
use App\Models\LiquorTaxMonthlyFilingLine;
use App\Models\LiquorTaxReliefSetting;
use App\Models\ReportExport;
use App\Models\SalesReturnLine;
use App\Models\ShipmentHeader;
use App\Models\ShipmentLiquorTaxEvidence;
use App\Services\Tax\ConfirmConsumptionTaxMonthlyFilingService;
use App\Services\Tax\ConfirmLiquorTaxMonthlyFilingService;
use App\Services\Tax\CreateConsumptionTaxMonthlyFilingDraftService;
use App\Services\Tax\CreateLiquorTaxFilingAdjustmentService;
use App\Services\Tax\CreateLiquorTaxMonthlyFilingDraftService;
use App\Services\Tax\CreateLiquorTaxReliefSettingService;
use App\Services\Tax\GenerateLiquorTaxFilingReportService;
use App\Services\Tax\RecordShipmentLiquorTaxEvidenceService;
use App\Services\Tax\ReopenLiquorTaxMonthlyFilingService;
use App\Services\Tax\ReviewSalesReturnLiquorTaxService;
use App\Services\Tax\UpdateLiquorTaxAdjustmentSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class TaxController extends ApiController
{
    public function liquorFilings(): JsonResponse
    {
        $latestFinalizedId = $this->latestFinalizedLiquorFilingId();
        $filings = LiquorTaxMonthlyFiling::query()
            ->with('lines')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit(50)
            ->get()
            ->map(fn (LiquorTaxMonthlyFiling $filing): array => $this->serializeLiquorFiling($filing, $latestFinalizedId))
            ->values()
            ->all();

        return $this->ok(['liquor_tax_monthly_filings' => $filings]);
    }

    public function liquorFiling(LiquorTaxMonthlyFiling $filing): JsonResponse
    {
        return $this->ok([
            'liquor_tax_monthly_filing' => $this->serializeLiquorFiling(
                $filing->load(['lines.sources', 'sources', 'reliefSetting', 'adjustments.category', 'adjustments.approvalRequest', 'adjustments.creator', 'reportExports']),
                $this->latestFinalizedLiquorFilingId(),
            ),
        ]);
    }

    public function liquorSettings(): JsonResponse
    {
        $adjustment = LiquorTaxAdjustmentSetting::query()->where('manufacturing_site_code', 'main')->first();

        return $this->ok([
            'liquor_tax_relief_settings' => LiquorTaxReliefSetting::query()
                ->orderByDesc('effective_from')->get()->map(fn (LiquorTaxReliefSetting $setting): array => $this->serializeReliefSetting($setting))->all(),
            'liquor_tax_adjustment_setting' => $adjustment ? $this->serializeAdjustmentSetting($adjustment) : null,
        ]);
    }

    public function createLiquorReliefSetting(
        StoreLiquorTaxReliefSettingRequest $request,
        CreateLiquorTaxReliefSettingService $service,
    ): JsonResponse {
        $setting = $service->create($request->validated(), $request->user());

        return $this->created(['liquor_tax_relief_setting' => $this->serializeReliefSetting($setting)]);
    }

    public function updateLiquorAdjustmentSetting(
        UpdateLiquorTaxAdjustmentSettingRequest $request,
        UpdateLiquorTaxAdjustmentSettingService $service,
    ): JsonResponse {
        $setting = $service->update($request->validated(), $request->user());

        return $this->ok(['liquor_tax_adjustment_setting' => $this->serializeAdjustmentSetting($setting)]);
    }

    public function createLiquorFiling(
        StoreTaxMonthlyFilingRequest $request,
        CreateLiquorTaxMonthlyFilingDraftService $service,
    ): JsonResponse {
        $validated = $request->validated();

        $filing = $service->create(
            year: (int) $validated['year'],
            month: (int) $validated['month'],
            reason: $validated['reason'] ?? null,
        );

        return $this->created(['liquor_tax_monthly_filing' => $this->serializeLiquorFiling($filing)]);
    }

    public function confirmLiquorFiling(
        ShipmentActionReasonRequest $request,
        LiquorTaxMonthlyFiling $filing,
        ConfirmLiquorTaxMonthlyFilingService $service,
    ): JsonResponse {
        $confirmed = $service->confirm($filing->year, $filing->month, $request->validated('reason'));

        return $this->ok(['liquor_tax_monthly_filing' => $this->serializeLiquorFiling($confirmed, $this->latestFinalizedLiquorFilingId())]);
    }

    public function reopenLiquorFiling(
        ShipmentActionReasonRequest $request,
        LiquorTaxMonthlyFiling $filing,
        ReopenLiquorTaxMonthlyFilingService $service,
    ): JsonResponse {
        $reopened = $service->reopen($filing, (string) $request->validated('reason'));

        return $this->ok(['liquor_tax_monthly_filing' => $this->serializeLiquorFiling($reopened, $this->latestFinalizedLiquorFilingId())]);
    }

    public function createLiquorAdjustment(
        StoreLiquorTaxAdjustmentRequest $request,
        LiquorTaxMonthlyFiling $filing,
        CreateLiquorTaxFilingAdjustmentService $service,
    ): JsonResponse {
        $adjustment = $service->create($filing, $request->user(), $request->validated());

        return $this->created(['liquor_tax_adjustment' => $this->serializeAdjustment($adjustment)]);
    }

    public function voidLiquorAdjustment(
        Request $request,
        LiquorTaxMonthlyFiling $filing,
        LiquorTaxMonthlyFilingAdjustment $adjustment,
        CreateLiquorTaxFilingAdjustmentService $service,
    ): JsonResponse {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        if ($adjustment->liquor_tax_monthly_filing_id !== $filing->id) {
            abort(404);
        }

        return $this->ok(['liquor_tax_adjustment' => $this->serializeAdjustment($service->void($adjustment, $request->user(), $validated['reason']))]);
    }

    public function createLiquorFilingExport(
        Request $request,
        LiquorTaxMonthlyFiling $filing,
        GenerateLiquorTaxFilingReportService $service,
    ): JsonResponse {
        $validated = $request->validate([
            'format' => ['required', 'in:xlsx,pdf'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $export = $service->generate($filing, $validated['format'], $validated['reason']);

        return $this->created(['report_export' => $this->serializeReportExport($export)]);
    }

    public function recordShipmentLiquorTaxEvidence(
        StoreShipmentLiquorTaxEvidenceRequest $request,
        ShipmentHeader $shipment,
        RecordShipmentLiquorTaxEvidenceService $service,
    ): JsonResponse {
        $evidence = $service->record($shipment, $request->user(), $request->validated());

        return $this->ok(['shipment_liquor_tax_evidence' => $this->serializeShipmentEvidence($evidence)]);
    }

    public function reviewSalesReturnLiquorTax(
        Request $request,
        SalesReturnLine $line,
        ReviewSalesReturnLiquorTaxService $service,
    ): JsonResponse {
        $validated = $request->validate([
            'treatment' => ['required', 'in:eligible,not_eligible,review'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $reviewed = $service->review($line, $request->user(), $validated['treatment'], $validated['reason']);

        return $this->ok(['sales_return_liquor_tax_review' => [
            'sales_return_line_id' => $reviewed->id,
            'treatment' => $reviewed->liquor_tax_return_treatment,
            'reason' => $reviewed->liquor_tax_return_reason,
            'reviewed_at' => $reviewed->liquor_tax_reviewed_at?->toISOString(),
        ]]);
    }

    public function downloadLiquorFilingExport(ReportExport $reportExport): BinaryFileResponse
    {
        $path = storage_path('app/'.$reportExport->file_path);
        if ($reportExport->report_type !== 'liquor_tax_filing' || ! is_file($path)) {
            abort(404);
        }

        return response()->download($path, $reportExport->file_name, [
            'Content-Type' => $reportExport->mime_type ?: 'application/octet-stream',
        ], ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    public function consumptionFilings(): JsonResponse
    {
        $filings = ConsumptionTaxMonthlyFiling::query()
            ->with('lines')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit(50)
            ->get()
            ->map(fn (ConsumptionTaxMonthlyFiling $filing): array => $this->serializeConsumptionFiling($filing))
            ->values()
            ->all();

        return $this->ok(['consumption_tax_monthly_filings' => $filings]);
    }

    public function consumptionFiling(ConsumptionTaxMonthlyFiling $filing): JsonResponse
    {
        return $this->ok([
            'consumption_tax_monthly_filing' => $this->serializeConsumptionFiling($filing->load('lines')),
        ]);
    }

    public function createConsumptionFiling(
        StoreTaxMonthlyFilingRequest $request,
        CreateConsumptionTaxMonthlyFilingDraftService $service,
    ): JsonResponse {
        $validated = $request->validated();

        $filing = $service->create(
            year: (int) $validated['year'],
            month: (int) $validated['month'],
            reason: $validated['reason'] ?? null,
        );

        return $this->created(['consumption_tax_monthly_filing' => $this->serializeConsumptionFiling($filing)]);
    }

    public function confirmConsumptionFiling(
        ShipmentActionReasonRequest $request,
        ConsumptionTaxMonthlyFiling $filing,
        ConfirmConsumptionTaxMonthlyFilingService $service,
    ): JsonResponse {
        $confirmed = $service->confirm($filing->year, $filing->month, $request->validated('reason'));

        return $this->ok(['consumption_tax_monthly_filing' => $this->serializeConsumptionFiling($confirmed)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLiquorFiling(LiquorTaxMonthlyFiling $filing, ?int $latestFinalizedId = null): array
    {
        return [
            'id' => $filing->id,
            'status' => $filing->status,
            'can_reopen' => $filing->status === 'confirmed' && $filing->id === $latestFinalizedId,
            'year' => $filing->year,
            'month' => $filing->month,
            'period_start' => $filing->period_start?->toDateString(),
            'period_end' => $filing->period_end?->toDateString(),
            'manufacturing_site_code' => $filing->manufacturing_site_code,
            'fiscal_year' => $filing->fiscal_year,
            'relief_scheme' => $filing->relief_scheme,
            'calculation_rule_version' => $filing->calculation_rule_version,
            'total_taxable_kl' => $filing->total_taxable_kl,
            'total_gross_tax_amount' => $filing->total_gross_tax_amount,
            'total_relief_amount' => $filing->total_relief_amount,
            'total_deduction_amount' => $filing->total_deduction_amount,
            'total_adjustment_taxable_kl' => $filing->total_adjustment_taxable_kl,
            'total_adjustment_amount' => $filing->total_adjustment_amount,
            'adjustment_count' => $filing->adjustment_count,
            'net_payable_amount' => $filing->net_payable_amount,
            'warning_count' => $filing->warning_count,
            'total_estimated_amount' => $filing->total_estimated_amount,
            'total_confirmed_amount' => $filing->total_confirmed_amount,
            'shipment_count' => $filing->shipment_count,
            'line_count' => $filing->line_count,
            'calculated_at' => $filing->calculated_at?->toISOString(),
            'confirmed_at' => $filing->confirmed_at?->toISOString(),
            'closed_at' => $filing->closed_at?->toISOString(),
            'reason' => $filing->reason,
            'lines' => $filing->lines
                ->map(fn (LiquorTaxMonthlyFilingLine $line): array => [
                    'id' => $line->id,
                    'line_no' => $line->line_no,
                    'liquor_tax_category_id' => $line->liquor_tax_category_id,
                    'liquor_tax_category_code' => $line->liquor_tax_category_code,
                    'liquor_tax_category_name' => $line->liquor_tax_category_name,
                    'liquor_taxability' => $line->liquor_taxability,
                    'liquor_tax_rule_id' => $line->liquor_tax_rule_id,
                    'tax_treatment' => $line->tax_treatment,
                    'source_type' => $line->source_type,
                    'reporting_alcohol_percentage' => $line->reporting_alcohol_percentage,
                    'calculation_method' => $line->calculation_method,
                    'tax_per_kl' => $line->tax_per_kl,
                    'reduction_rate' => $line->reduction_rate,
                    'taxable_kl' => $line->taxable_kl,
                    'estimated_amount' => $line->estimated_amount,
                    'gross_tax_amount' => $line->gross_tax_amount,
                    'relief_eligible_kl' => $line->relief_eligible_kl,
                    'relief_amount' => $line->relief_amount,
                    'deduction_amount' => $line->deduction_amount,
                    'net_tax_amount' => $line->net_tax_amount,
                    'cumulative_gross_before' => $line->cumulative_gross_before,
                    'cumulative_gross_after' => $line->cumulative_gross_after,
                    'relief_calculation_basis' => $line->relief_calculation_basis,
                    'requires_review' => $line->requires_review,
                    'confirmed_amount' => $line->confirmed_amount,
                    'shipment_count' => $line->shipment_count,
                    'line_count' => $line->line_count,
                    'sources' => $line->relationLoaded('sources') ? $line->sources->map(fn ($source): array => [
                        'id' => $source->id,
                        'source_type' => $source->source_type,
                        'source_header_id' => $source->source_header_id,
                        'source_line_id' => $source->source_line_id,
                        'source_document_number' => $source->source_document_number,
                        'source_date' => $source->source_date?->toDateString(),
                        'tax_treatment' => $source->tax_treatment,
                        'reporting_alcohol_percentage' => $source->reporting_alcohol_percentage,
                        'quantity' => $source->quantity,
                        'taxable_kl' => $source->taxable_kl,
                        'gross_tax_amount' => $source->gross_tax_amount,
                        'requires_review' => $source->requires_review,
                        'review_reason' => $source->review_reason,
                        'evidence_status' => $source->evidence_status,
                        'evidence_reference' => $source->evidence_reference,
                    ])->values()->all() : [],
                ])
                ->values()
                ->all(),
            'adjustments' => $filing->relationLoaded('adjustments') ? $filing->adjustments->map(fn (LiquorTaxMonthlyFilingAdjustment $adjustment): array => $this->serializeAdjustment($adjustment))->values()->all() : [],
            'report_exports' => $filing->relationLoaded('reportExports') ? $filing->reportExports->sortByDesc('id')->map(fn (ReportExport $export): array => $this->serializeReportExport($export))->values()->all() : [],
        ];
    }

    private function latestFinalizedLiquorFilingId(): ?int
    {
        return LiquorTaxMonthlyFiling::query()
            ->whereIn('status', ['confirmed', 'closed'])
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->value('id');
    }

    private function serializeAdjustment(LiquorTaxMonthlyFilingAdjustment $adjustment): array
    {
        return [
            'id' => $adjustment->id, 'line_no' => $adjustment->line_no, 'status' => $adjustment->status,
            'adjustment_type' => $adjustment->adjustment_type, 'liquor_tax_category_id' => $adjustment->liquor_tax_category_id,
            'liquor_tax_category_name' => $adjustment->category?->name, 'description' => $adjustment->description,
            'taxable_kl_adjustment' => $adjustment->taxable_kl_adjustment, 'tax_amount_adjustment' => $adjustment->tax_amount_adjustment,
            'approval_required' => $adjustment->approval_required, 'approval_request_id' => $adjustment->approval_request_id,
            'approval_status' => $adjustment->approvalRequest?->status, 'created_by_name' => $adjustment->creator?->name,
            'created_at' => $adjustment->created_at?->toISOString(), 'voided_at' => $adjustment->voided_at?->toISOString(), 'void_reason' => $adjustment->void_reason,
        ];
    }

    private function serializeReportExport(ReportExport $export): array
    {
        return [
            'id' => $export->id, 'format' => $export->format, 'status' => $export->status,
            'file_name' => $export->file_name, 'file_size' => $export->file_size, 'mime_type' => $export->mime_type,
            'checksum_sha256' => $export->checksum_sha256, 'generated_at' => $export->generated_at?->toISOString(),
            'reason' => $export->reason, 'download_url' => "/api/v1/tax/report-exports/{$export->id}/download",
        ];
    }

    private function serializeShipmentEvidence(ShipmentLiquorTaxEvidence $evidence): array
    {
        return [
            'id' => $evidence->id,
            'shipment_header_id' => $evidence->shipment_header_id,
            'tax_treatment' => $evidence->tax_treatment,
            'status' => $evidence->status,
            'evidence_reference' => $evidence->evidence_reference,
            'evidence_date' => $evidence->evidence_date?->toDateString(),
            'destination' => $evidence->destination,
            'customs_office' => $evidence->customs_office,
            'exporter_type' => $evidence->exporter_type,
            'note' => $evidence->note,
            'confirmed_by_name' => $evidence->confirmer?->name,
            'confirmed_at' => $evidence->confirmed_at?->toISOString(),
        ];
    }

    private function serializeReliefSetting(LiquorTaxReliefSetting $setting): array
    {
        return [
            'id' => $setting->id,
            'manufacturing_site_code' => $setting->manufacturing_site_code,
            'scheme' => $setting->scheme,
            'effective_from' => $setting->effective_from?->toDateString(),
            'effective_to' => $setting->effective_to?->toDateString(),
            'legacy_reduction_rate' => $setting->legacy_reduction_rate,
            'legacy_annual_quantity_limit_kl' => $setting->legacy_annual_quantity_limit_kl,
            'opening_eligible_quantity_kl' => $setting->opening_eligible_quantity_kl,
            'opening_gross_tax_amount' => $setting->opening_gross_tax_amount,
            'prior_year_total_taxable_quantity_kl' => $setting->prior_year_total_taxable_quantity_kl,
            'prior_year_peak_taxable_quantity_kl' => $setting->prior_year_peak_taxable_quantity_kl,
            'approval_date' => $setting->approval_date?->toDateString(),
            'approval_reference' => $setting->approval_reference,
            'selection_notice_date' => $setting->selection_notice_date?->toDateString(),
            'discontinuance_notice_date' => $setting->discontinuance_notice_date?->toDateString(),
            'calculation_rule_version' => $setting->calculation_rule_version,
            'note' => $setting->note,
            'is_active' => $setting->is_active,
        ];
    }

    private function serializeAdjustmentSetting(LiquorTaxAdjustmentSetting $setting): array
    {
        return [
            'id' => $setting->id,
            'manufacturing_site_code' => $setting->manufacturing_site_code,
            'approval_amount_threshold' => $setting->approval_amount_threshold,
            'approval_quantity_threshold_kl' => $setting->approval_quantity_threshold_kl,
            'is_active' => $setting->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeConsumptionFiling(ConsumptionTaxMonthlyFiling $filing): array
    {
        return [
            'id' => $filing->id,
            'status' => $filing->status,
            'year' => $filing->year,
            'month' => $filing->month,
            'period_start' => $filing->period_start?->toDateString(),
            'period_end' => $filing->period_end?->toDateString(),
            'total_taxable_amount' => $filing->total_taxable_amount,
            'total_tax_amount' => $filing->total_tax_amount,
            'total_confirmed_tax_amount' => $filing->total_confirmed_tax_amount,
            'total_amount' => $filing->total_amount,
            'invoice_count' => $filing->invoice_count,
            'line_count' => $filing->line_count,
            'calculated_at' => $filing->calculated_at?->toISOString(),
            'confirmed_at' => $filing->confirmed_at?->toISOString(),
            'closed_at' => $filing->closed_at?->toISOString(),
            'reason' => $filing->reason,
            'lines' => $filing->lines
                ->map(fn (ConsumptionTaxMonthlyFilingLine $line): array => [
                    'id' => $line->id,
                    'line_no' => $line->line_no,
                    'consumption_tax_category_id' => $line->consumption_tax_category_id,
                    'consumption_tax_category_code' => $line->consumption_tax_category_code,
                    'consumption_tax_category_name' => $line->consumption_tax_category_name,
                    'consumption_taxability' => $line->consumption_taxability,
                    'consumption_tax_rate_id' => $line->consumption_tax_rate_id,
                    'tax_rate' => $line->tax_rate,
                    'consumption_tax_rate_effective_from' => $line->consumption_tax_rate_effective_from?->toDateString(),
                    'taxable_amount' => $line->taxable_amount,
                    'tax_amount' => $line->tax_amount,
                    'confirmed_tax_amount' => $line->confirmed_tax_amount,
                    'total_amount' => $line->total_amount,
                    'invoice_count' => $line->invoice_count,
                    'line_count' => $line->line_count,
                ])
                ->values()
                ->all(),
        ];
    }
}
