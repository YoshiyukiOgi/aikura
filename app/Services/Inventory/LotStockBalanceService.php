<?php

namespace App\Services\Inventory;

use App\Models\ShipmentLotAllocation;
use App\Models\StockLotMonthlyBalance;
use App\Models\StockMovement;
use Illuminate\Support\Collection;

class LotStockBalanceService
{
    public function forLotLocationUnit(int $productionLotId, int $stockLocationId, int $unitId): LotStockBalance
    {
        $base = StockLotMonthlyBalance::query()
            ->where('production_lot_id', $productionLotId)
            ->where('stock_location_id', $stockLocationId)
            ->where('unit_id', $unitId)
            ->whereIn('status', ['confirmed', 'closed'])
            ->orderByDesc('period_end')
            ->first();
        $query = StockMovement::query()
            ->where('production_lot_id', $productionLotId)
            ->where('stock_location_id', $stockLocationId)
            ->where('unit_id', $unitId)
            ->whereIn('status', ['confirmed', 'closed'])
            ->whereNull('cancelled_at');
        if ($base) {
            $query->whereDate('movement_date', '>', $base->period_end);
        }
        $physical = bcadd($base ? (string) $base->closing_quantity : '0', (string) $query->sum('quantity'), 4);

        return $this->makeBalance($productionLotId, $stockLocationId, $unitId, $physical);
    }

    /** @return Collection<int, LotStockBalance> */
    public function all(): Collection
    {
        $latestPeriodEnd = StockLotMonthlyBalance::query()->whereIn('status', ['confirmed', 'closed'])->max('period_end');
        if (! $latestPeriodEnd) {
            return $this->allFromMovements();
        }

        $quantities = StockLotMonthlyBalance::query()
            ->whereIn('status', ['confirmed', 'closed'])
            ->whereDate('period_end', $latestPeriodEnd)
            ->get()
            ->mapWithKeys(fn (StockLotMonthlyBalance $row): array => [
                $this->key($row->production_lot_id, $row->stock_location_id, $row->unit_id) => [
                    'production_lot_id' => $row->production_lot_id,
                    'stock_location_id' => $row->stock_location_id,
                    'unit_id' => $row->unit_id,
                    'quantity' => (string) $row->closing_quantity,
                ],
            ]);

        StockMovement::query()
            ->selectRaw('production_lot_id, stock_location_id, unit_id, COALESCE(SUM(quantity), 0) as physical_quantity')
            ->whereNotNull('production_lot_id')
            ->whereIn('status', ['confirmed', 'closed'])
            ->whereNull('cancelled_at')
            ->whereDate('movement_date', '>', $latestPeriodEnd)
            ->groupBy('production_lot_id', 'stock_location_id', 'unit_id')
            ->get()
            ->each(function (object $row) use ($quantities): void {
                $key = $this->key($row->production_lot_id, $row->stock_location_id, $row->unit_id);
                $item = $quantities->get($key, [
                    'production_lot_id' => $row->production_lot_id,
                    'stock_location_id' => $row->stock_location_id,
                    'unit_id' => $row->unit_id,
                    'quantity' => '0.0000',
                ]);
                $item['quantity'] = bcadd($item['quantity'], (string) $row->physical_quantity, 4);
                $quantities->put($key, $item);
            });

        return $quantities->values()->map(fn (array $row): LotStockBalance => $this->makeBalance(
            (int) $row['production_lot_id'], (int) $row['stock_location_id'], (int) $row['unit_id'], $row['quantity'],
        ))->values();
    }

    /** @return Collection<int, LotStockBalance> */
    public function allAsOf(string $asOfDate, bool $includeZeroStock = false): Collection
    {
        if ($asOfDate >= now()->toDateString()) {
            return $this->all();
        }

        $query = StockMovement::query()
            ->selectRaw('production_lot_id, stock_location_id, unit_id, COALESCE(SUM(quantity), 0) as physical_quantity')
            ->whereNotNull('production_lot_id')
            ->whereIn('status', ['confirmed', 'closed'])
            ->whereNull('cancelled_at')
            ->whereDate('movement_date', '<=', $asOfDate)
            ->groupBy('production_lot_id', 'stock_location_id', 'unit_id');

        if (! $includeZeroStock) {
            $query->havingRaw('ABS(SUM(quantity)) > 0.00005');
        }

        return $query
            ->orderBy('production_lot_id')->orderBy('stock_location_id')->orderBy('unit_id')
            ->get()
            ->map(fn (object $row): LotStockBalance => $this->makeAsOfBalance(
                (int) $row->production_lot_id,
                (int) $row->stock_location_id,
                (int) $row->unit_id,
                (string) $row->physical_quantity,
            ));
    }

    private function allFromMovements(): Collection
    {
        return StockMovement::query()
            ->selectRaw('production_lot_id, stock_location_id, unit_id, COALESCE(SUM(quantity), 0) as physical_quantity')
            ->whereNotNull('production_lot_id')
            ->whereIn('status', ['confirmed', 'closed'])
            ->whereNull('cancelled_at')
            ->groupBy('production_lot_id', 'stock_location_id', 'unit_id')
            ->orderBy('production_lot_id')->orderBy('stock_location_id')->orderBy('unit_id')
            ->get()
            ->map(fn (object $row): LotStockBalance => $this->makeBalance(
                (int) $row->production_lot_id, (int) $row->stock_location_id, (int) $row->unit_id, (string) $row->physical_quantity,
            ));
    }

    private function key(int $lotId, int $locationId, int $unitId): string
    {
        return implode(':', [$lotId, $locationId, $unitId]);
    }

    private function makeBalance(int $lotId, int $locationId, int $unitId, string $physical): LotStockBalance
    {
        $physical = bcadd($physical, '0', 4);
        $allocated = bcadd((string) ShipmentLotAllocation::query()
            ->where('production_lot_id', $lotId)
            ->where('stock_location_id', $locationId)
            ->where('unit_id', $unitId)
            ->where('status', 'allocated')
            ->whereNull('cancelled_at')
            ->sum('quantity'), '0', 4);

        return new LotStockBalance(
            productionLotId: $lotId,
            stockLocationId: $locationId,
            unitId: $unitId,
            physicalQuantity: $physical,
            reservedQuantity: '0.0000',
            allocatedQuantity: $allocated,
            availableQuantity: bcsub($physical, $allocated, 4),
        );
    }

    private function makeAsOfBalance(int $lotId, int $locationId, int $unitId, string $physical): LotStockBalance
    {
        $physical = bcadd($physical, '0', 4);

        return new LotStockBalance(
            productionLotId: $lotId,
            stockLocationId: $locationId,
            unitId: $unitId,
            physicalQuantity: $physical,
            reservedQuantity: '0.0000',
            allocatedQuantity: '0.0000',
            availableQuantity: $physical,
        );
    }
}
