<?php

namespace App\Services\Billing;

use App\Exceptions\Billing\ReceivableMonthlyBalanceCloseException;
use App\Models\ReceivableMonthlyBalance;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CloseReceivableMonthlyBalanceService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @return Collection<int, ReceivableMonthlyBalance>
     */
    public function close(int $year, int $month, string $reason): Collection
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ReceivableMonthlyBalanceCloseException::emptyReason();
        }

        return DB::transaction(function () use ($year, $month, $reason): Collection {
            $balances = ReceivableMonthlyBalance::query()
                ->where('year', $year)
                ->where('month', $month)
                ->lockForUpdate()
                ->get();

            if ($balances->isEmpty()) {
                throw ReceivableMonthlyBalanceCloseException::noConfirmedBalances($year, $month);
            }

            if ($balances->contains(fn (ReceivableMonthlyBalance $balance): bool => $balance->status !== 'confirmed')) {
                throw ReceivableMonthlyBalanceCloseException::containsNonConfirmedBalances($year, $month);
            }

            $closedAt = now();

            foreach ($balances as $balance) {
                $balance->forceFill([
                    'status' => 'closed',
                    'closed_at' => $closedAt,
                    'reason' => $reason,
                ])->save();
            }

            $this->auditLogService->record(new AuditLogData(
                event: 'receivable_monthly_balance.closed',
                targetTable: 'receivable_monthly_balances',
                targetId: "{$year}-".str_pad((string) $month, 2, '0', STR_PAD_LEFT),
                afterValues: [
                    'year' => $year,
                    'month' => $month,
                    'status' => 'closed',
                    'balance_count' => $balances->count(),
                    'scheduled_amount' => $this->sum($balances, 'scheduled_amount'),
                    'received_amount' => $this->sum($balances, 'received_amount'),
                    'outstanding_amount' => $this->sum($balances, 'outstanding_amount'),
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

    /**
     * @param Collection<int, ReceivableMonthlyBalance> $balances
     */
    private function sum(Collection $balances, string $column): string
    {
        return $balances->reduce(
            fn (string $carry, ReceivableMonthlyBalance $balance): string => bcadd($carry, (string) $balance->{$column}, 2),
            '0.00',
        );
    }
}
