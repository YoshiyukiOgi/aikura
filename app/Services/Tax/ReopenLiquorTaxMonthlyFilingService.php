<?php

namespace App\Services\Tax;

use App\Exceptions\Tax\LiquorTaxMonthlyFilingReopenException;
use App\Models\LiquorTaxMonthlyFiling;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

class ReopenLiquorTaxMonthlyFilingService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function reopen(LiquorTaxMonthlyFiling $filing, string $reason): LiquorTaxMonthlyFiling
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw LiquorTaxMonthlyFilingReopenException::emptyReason();
        }

        return DB::transaction(function () use ($filing, $reason): LiquorTaxMonthlyFiling {
            $lockedFiling = LiquorTaxMonthlyFiling::query()
                ->whereKey($filing->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedFiling->status !== 'confirmed') {
                throw LiquorTaxMonthlyFilingReopenException::nonConfirmedFiling(
                    $lockedFiling->year,
                    $lockedFiling->month,
                );
            }

            $latestFinalized = LiquorTaxMonthlyFiling::query()
                ->whereIn('status', ['confirmed', 'closed'])
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->lockForUpdate()
                ->first();

            if ($latestFinalized?->getKey() !== $lockedFiling->getKey()) {
                throw LiquorTaxMonthlyFilingReopenException::notLatestFinalizedFiling(
                    $lockedFiling->year,
                    $lockedFiling->month,
                );
            }

            $lockedFiling->load([
                'lines.sources',
                'sources',
                'adjustments.approvalRequest',
                'reportExports',
            ]);
            $confirmedSnapshot = $lockedFiling->toArray();

            $lockedFiling->lines()->update(['confirmed_amount' => null]);
            $lockedFiling->forceFill([
                'status' => 'draft',
                'total_confirmed_amount' => null,
                'confirmed_at' => null,
                'closed_at' => null,
                'reason' => $reason,
            ])->save();

            $this->auditLogService->record(new AuditLogData(
                event: 'liquor_tax_monthly_filing.reopened',
                auditable: $lockedFiling,
                beforeValues: $confirmedSnapshot,
                afterValues: [
                    'year' => $lockedFiling->year,
                    'month' => $lockedFiling->month,
                    'status' => 'draft',
                    'total_confirmed_amount' => null,
                    'confirmed_at' => null,
                ],
                reason: $reason,
            ));

            return $lockedFiling->refresh()->load([
                'lines.sources',
                'sources',
                'reliefSetting',
                'adjustments.category',
                'adjustments.approvalRequest',
                'adjustments.creator',
                'reportExports',
            ]);
        });
    }
}
