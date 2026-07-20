<?php

namespace App\Services\Tax;

use App\Exceptions\Tax\ConsumptionTaxMonthlyFilingConfirmationException;
use App\Models\ConsumptionTaxMonthlyFiling;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

class ConfirmConsumptionTaxMonthlyFilingService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function confirm(int $year, int $month, string $reason): ConsumptionTaxMonthlyFiling
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ConsumptionTaxMonthlyFilingConfirmationException::emptyReason();
        }

        return DB::transaction(function () use ($year, $month, $reason): ConsumptionTaxMonthlyFiling {
            $filing = ConsumptionTaxMonthlyFiling::query()
                ->with('lines')
                ->where('year', $year)
                ->where('month', $month)
                ->lockForUpdate()
                ->first();

            if ($filing === null) {
                throw ConsumptionTaxMonthlyFilingConfirmationException::noDraftFiling($year, $month);
            }

            if ($filing->status !== 'draft') {
                throw ConsumptionTaxMonthlyFilingConfirmationException::nonDraftFiling($year, $month);
            }

            $confirmedAt = now();

            foreach ($filing->lines as $line) {
                $line->forceFill([
                    'confirmed_tax_amount' => $line->tax_amount,
                ])->save();
            }

            $filing->forceFill([
                'status' => 'confirmed',
                'total_confirmed_tax_amount' => $filing->total_tax_amount,
                'confirmed_at' => $confirmedAt,
                'reason' => $reason,
            ])->save();

            $this->auditLogService->record(new AuditLogData(
                event: 'consumption_tax_monthly_filing.confirmed',
                auditable: $filing->refresh(),
                afterValues: [
                    'year' => $year,
                    'month' => $month,
                    'status' => 'confirmed',
                    'total_taxable_amount' => $filing->total_taxable_amount,
                    'total_confirmed_tax_amount' => $filing->total_confirmed_tax_amount,
                    'invoice_count' => $filing->invoice_count,
                    'line_count' => $filing->line_count,
                ],
                reason: $reason,
            ));

            return $filing->refresh()->load('lines');
        });
    }
}
