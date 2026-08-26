<?php

namespace App\Services\Inventory;

use App\Models\ShipmentLotAllocation;
use App\Models\StockLotMonthlyBalance;
use App\Models\StockMovement;
use App\Services\Operations\OperationalPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LotStockBalanceService
{
    public function __construct(private readonly OperationalPeriod $operationalPeriod) {}

    public function forLotLocationUnit(int $productionLotId, int $stockLocationId, int $unitId): LotStockBalance
    {
        $asOfDate = now()->toDateString();
        $base = $this->latestMonthlyBalance($asOfDate, $productionLotId, $stockLocationId, $unitId);
        $latestPeriodEnd = $base?->period_end?->toDateString()
            ?? $this->latestMonthlyBalance($asOfDate)?->period_end?->toDateString();
        $movementStartDate = $latestPeriodEnd ?? $this->operationalPeriod->startDate();
        $quantity = $base ? (string) $base->closing_quantity : '0';

        $movementQuery = StockMovement::query()
            ->where('production_lot_id', $productionLotId)
            ->where('stock_location_id', $stockLocationId)
            ->where('unit_id', $unitId)
            ->whereIn('status', ['confirmed', 'closed'])
            ->whereNull('cancelled_at')
            ->whereDate('movement_date', $latestPeriodEnd ? '>' : '>=', $movementStartDate)
            ->whereDate('movement_date', '<=', $asOfDate);
        $this->excludePostClosingOpeningStock($movementQuery, $latestPeriodEnd);
        $movementQuantity = $movementQuery->sum('quantity');
        $physical = bcadd($quantity, (string) $movementQuantity, 4);

        return $this->makeBalance($productionLotId, $stockLocationId, $unitId, $physical);
    }

    private function latestMonthlyBalance(
        string $asOfDate,
        ?int $productionLotId = null,
        ?int $stockLocationId = null,
        ?int $unitId = null,
    ): ?StockLotMonthlyBalance {
        return StockLotMonthlyBalance::query()
            ->when($productionLotId !== null, fn ($query) => $query->where('production_lot_id', $productionLotId))
            ->when($stockLocationId !== null, fn ($query) => $query->where('stock_location_id', $stockLocationId))
            ->when($unitId !== null, fn ($query) => $query->where('unit_id', $unitId))
            ->whereIn('status', ['confirmed', 'closed'])
            ->whereDate('period_end', '>=', $this->operationalPeriod->startDate())
            ->whereDate('period_end', '<=', $asOfDate)
            ->orderByDesc('period_end')
            ->first();
    }

    /**
     * @return Collection<int, LotStockBalance>
     */
    private function balancesAsOf(string $asOfDate, bool $includeAllocated, bool $includeZeroStock = false): Collection
    {
        $latestPeriodEnd = $this->latestMonthlyBalance($asOfDate)?->period_end?->toDateString();
        if (! $latestPeriodEnd) {
            return $this->allFromMovements($asOfDate, $includeAllocated, $includeZeroStock);
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
            ->whereDate('movement_date', '<=', $asOfDate)
            ->where('movement_type', '<>', 'opening_stock')
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

        return $quantities
            ->filter(fn (array $row): bool => $includeZeroStock || bccomp((string) $row['quantity'], '0.0000', 4) !== 0)
            ->values()
            ->map(fn (array $row): LotStockBalance => $includeAllocated
                ? $this->makeBalance((int) $row['production_lot_id'], (int) $row['stock_location_id'], (int) $row['unit_id'], $row['quantity'])
                : $this->makeAsOfBalance((int) $row['production_lot_id'], (int) $row['stock_location_id'], (int) $row['unit_id'], $row['quantity']))
            ->values();
    }

    /** @return Collection<int, LotStockBalance> */
    public function all(): Collection
    {
        return $this->balancesAsOf(now()->toDateString(), true);
    }

    /** @return Collection<int, LotStockBalance> */
    public function allAsOf(string $asOfDate, bool $includeZeroStock = false): Collection
    {
        return $this->balancesAsOf($asOfDate, $asOfDate === now()->toDateString(), $includeZeroStock)
            ->sortBy([
                ['productionLotId', 'asc'],
                ['stockLocationId', 'asc'],
                ['unitId', 'asc'],
            ])
            ->values();
    }

    private function allFromMovements(string $asOfDate, bool $includeAllocated, bool $includeZeroStock = false): Collection
    {
        $query = StockMovement::query()
            ->selectRaw('production_lot_id, stock_location_id, unit_id, COALESCE(SUM(quantity), 0) as physical_quantity')
            ->whereNotNull('production_lot_id')
            ->whereIn('status', ['confirmed', 'closed'])
            ->whereNull('cancelled_at')
            ->whereDate('movement_date', '>=', $this->operationalPeriod->startDate())
            ->whereDate('movement_date', '<=', $asOfDate)
            ->groupBy('production_lot_id', 'stock_location_id', 'unit_id')
            ->orderBy('production_lot_id')->orderBy('stock_location_id')->orderBy('unit_id');

        if (! $includeZeroStock) {
            $query->havingRaw('ABS(SUM(quantity)) > 0.00005');
        }

        return $query->get()
            ->map(fn (object $row): LotStockBalance => $includeAllocated
                ? $this->makeBalance((int) $row->production_lot_id, (int) $row->stock_location_id, (int) $row->unit_id, (string) $row->physical_quantity)
                : $this->makeAsOfBalance((int) $row->production_lot_id, (int) $row->stock_location_id, (int) $row->unit_id, (string) $row->physical_quantity));
    }

    private function excludePostClosingOpeningStock(Builder $query, ?string $latestPeriodEnd): void
    {
        if ($latestPeriodEnd !== null) {
            $query->where('movement_type', '<>', 'opening_stock');
        }
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
