<?php

namespace App\Services\Inventory;

use App\Exceptions\Inventory\StockMonthlyBalanceConfirmationException;
use App\Models\StockLotMonthlyBalance;
use App\Models\StockMovement;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConfirmStockMonthlyBalanceService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /** @return Collection<int, StockLotMonthlyBalance> */
    public function confirm(int $year, int $month, string $reason): Collection
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw StockMonthlyBalanceConfirmationException::emptyReason();
        }

        $periodStart = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $periodEnd = $periodStart->endOfMonth();

        return DB::transaction(function () use ($year, $month, $periodStart, $periodEnd, $reason): Collection {
            $balances = StockLotMonthlyBalance::query()
                ->where('year', $year)->where('month', $month)
                ->lockForUpdate()->get();

            if ($balances->isEmpty()) {
                throw StockMonthlyBalanceConfirmationException::noDraftBalances($year, $month);
            }
            if ($balances->contains(fn (StockLotMonthlyBalance $balance): bool => $balance->status !== 'draft')) {
                throw StockMonthlyBalanceConfirmationException::containsNonDraftBalances($year, $month);
            }

            $confirmedAt = now();
            foreach ($balances as $balance) {
                $balance->update(['status' => 'confirmed', 'confirmed_at' => $confirmedAt]);
            }

            $closedMovementCount = StockMovement::query()
                ->where('status', 'confirmed')->whereNull('cancelled_at')
                ->whereBetween('movement_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                ->update(['status' => 'closed', 'closed_at' => $confirmedAt, 'updated_at' => $confirmedAt]);

            $this->auditLogService->record(new AuditLogData(
                event: 'stock_lot_monthly_balance.confirmed',
                targetTable: 'stock_lot_monthly_balances',
                targetId: "{$year}-".str_pad((string) $month, 2, '0', STR_PAD_LEFT),
                afterValues: ['year' => $year, 'month' => $month, 'status' => 'confirmed', 'balance_count' => $balances->count(), 'closed_stock_movement_count' => $closedMovementCount],
                reason: $reason,
            ));

            return StockLotMonthlyBalance::query()
                ->where('year', $year)->where('month', $month)
                ->orderBy('production_lot_id')->orderBy('stock_location_id')->orderBy('unit_id')->get();
        });
    }
}
