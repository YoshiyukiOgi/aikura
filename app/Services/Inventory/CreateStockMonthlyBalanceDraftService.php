<?php

namespace App\Services\Inventory;

use App\Models\StockLotMonthlyBalance;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreateStockMonthlyBalanceDraftService
{
    /** @return Collection<int, StockLotMonthlyBalance> */
    public function create(int $year, int $month, ?string $reason = null): Collection
    {
        if (StockLotMonthlyBalance::query()
            ->where('year', $year)->where('month', $month)
            ->whereIn('status', ['confirmed', 'closed'])->exists()) {
            throw new DomainException('確定済みの月次ロット在庫残高は再計算できません。');
        }

        $periodStart = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $periodEnd = $periodStart->endOfMonth();

        return DB::transaction(function () use ($year, $month, $periodStart, $periodEnd): Collection {
            $rows = StockMovement::query()
                ->selectRaw('production_lot_id, stock_location_id, unit_id, COALESCE(SUM(quantity), 0) as closing_quantity')
                ->whereNotNull('production_lot_id')
                ->whereIn('status', ['confirmed', 'closed'])
                ->whereNull('cancelled_at')
                ->whereDate('movement_date', '<=', $periodEnd->toDateString())
                ->groupBy('production_lot_id', 'stock_location_id', 'unit_id')
                ->get();

            $balances = collect();
            foreach ($rows as $row) {
                $balances->push(StockLotMonthlyBalance::updateOrCreate(
                    [
                        'year' => $year,
                        'month' => $month,
                        'production_lot_id' => $row->production_lot_id,
                        'stock_location_id' => $row->stock_location_id,
                        'unit_id' => $row->unit_id,
                    ],
                    [
                        'status' => 'draft',
                        'period_start' => $periodStart->toDateString(),
                        'period_end' => $periodEnd->toDateString(),
                        'closing_quantity' => bcadd((string) $row->closing_quantity, '0', 4),
                        'calculated_at' => now(),
                        'confirmed_at' => null,
                    ],
                )->refresh());
            }

            return $balances;
        });
    }
}
