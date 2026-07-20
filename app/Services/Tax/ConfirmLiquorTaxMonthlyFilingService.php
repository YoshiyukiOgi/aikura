<?php

namespace App\Services\Tax;

use App\Exceptions\Tax\LiquorTaxMonthlyFilingConfirmationException;
use App\Models\LiquorTaxMonthlyFiling;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

class ConfirmLiquorTaxMonthlyFilingService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function confirm(int $year, int $month, string $reason): LiquorTaxMonthlyFiling
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw LiquorTaxMonthlyFilingConfirmationException::emptyReason();
        }

        return DB::transaction(function () use ($year, $month, $reason): LiquorTaxMonthlyFiling {
            $filing = LiquorTaxMonthlyFiling::query()
                ->with(['lines', 'adjustments.approvalRequest'])
                ->where('year', $year)
                ->where('month', $month)
                ->lockForUpdate()
                ->first();

            if ($filing === null) {
                throw LiquorTaxMonthlyFilingConfirmationException::noDraftFiling($year, $month);
            }

            if ($filing->status !== 'draft') {
                throw LiquorTaxMonthlyFilingConfirmationException::nonDraftFiling($year, $month);
            }

            $unapproved = $filing->adjustments
                ->where('status', 'active')
                ->where('approval_required', true)
                ->first(fn ($adjustment): bool => $adjustment->approvalRequest?->status !== 'approved');
            if ($unapproved !== null) {
                throw new \DomainException("調整明細 {$unapproved->line_no} の承認が完了していません。");
            }

            $confirmedAt = now();

            foreach ($filing->lines as $line) {
                $line->forceFill([
                    'confirmed_amount' => $line->estimated_amount,
                ])->save();
            }

            $filing->forceFill([
                'status' => 'confirmed',
                'total_confirmed_amount' => $filing->net_payable_amount,
                'confirmed_at' => $confirmedAt,
                'reason' => $reason,
            ])->save();

            $this->auditLogService->record(new AuditLogData(
                event: 'liquor_tax_monthly_filing.confirmed',
                auditable: $filing->refresh(),
                afterValues: [
                    'year' => $year,
                    'month' => $month,
                    'status' => 'confirmed',
                    'total_taxable_kl' => $filing->total_taxable_kl,
                    'total_confirmed_amount' => $filing->total_confirmed_amount,
                    'line_count' => $filing->line_count,
                    'shipment_count' => $filing->shipment_count,
                ],
                reason: $reason,
            ));

            return $filing->refresh()->load(['lines', 'adjustments.approvalRequest']);
        });
    }
}
