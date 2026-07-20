<?php

namespace App\Services\Billing;

use App\Exceptions\Billing\ReceivableMonthlyBalanceConfirmationException;
use App\Models\ReceivableMonthlyBalance;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConfirmReceivableMonthlyBalanceService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @return Collection<int, ReceivableMonthlyBalance>
     */
    public function confirm(int $year, int $month, string $reason): Collection
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ReceivableMonthlyBalanceConfirmationException::emptyReason();
        }

        return DB::transaction(function () use ($year, $month, $reason): Collection {
            $balances = ReceivableMonthlyBalance::query()
                ->where('year', $year)
                ->where('month', $month)
                ->lockForUpdate()
                ->get();

            if ($balances->isEmpty()) {
                throw ReceivableMonthlyBalanceConfirmationException::noDraftBalances($year, $month);
            }

            if ($balances->contains(fn (ReceivableMonthlyBalance $balance): bool => $balance->status !== 'draft')) {
                throw ReceivableMonthlyBalanceConfirmationException::containsNonDraftBalances($year, $month);
            }

            $confirmedAt = now();

            foreach ($balances as $balance) {
                $balance->forceFill([
                    'status' => 'confirmed',
                    'confirmed_at' => $confirmedAt,
                    'reason' => $reason,
                ])->save();
            }

            $this->auditLogService->record(new AuditLogData(
                event: 'receivable_monthly_balance.confirmed',
                targetTable: 'receivable_monthly_balances',
                targetId: "{$year}-".str_pad((string) $month, 2, '0', STR_PAD_LEFT),
                afterValues: [
                    'year' => $year,
                    'month' => $month,
                    'status' => 'confirmed',
                    'balance_count' => $balances->count(),
                    'scheduled_amount' => $balances->reduce(
                        fn (string $carry, ReceivableMonthlyBalance $balance): string => bcadd($carry, (string) $balance->scheduled_amount, 2),
                        '0.00',
                    ),
                    'received_amount' => $balances->reduce(
                        fn (string $carry, ReceivableMonthlyBalance $balance): string => bcadd($carry, (string) $balance->received_amount, 2),
                        '0.00',
                    ),
                    'outstanding_amount' => $balances->reduce(
                        fn (string $carry, ReceivableMonthlyBalance $balance): string => bcadd($carry, (string) $balance->outstanding_amount, 2),
                        '0.00',
                    ),
                ],
                reason: $reason,
            ));

            return ReceivableMonthlyBalance::query()
                ->where('year', $year)
                ->where('month', $month)
                ->orderBy('customer_code')
                ->get();
        });
    }
}
