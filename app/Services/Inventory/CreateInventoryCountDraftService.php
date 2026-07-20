<?php

namespace App\Services\Inventory;

use App\Exceptions\Inventory\InventoryCountException;
use App\Models\InventoryCountHeader;
use App\Models\StockLotMonthlyBalance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CreateInventoryCountDraftService
{
    public function create(int $year, int $month, ?string $note = null): InventoryCountHeader
    {
        $countDate = CarbonImmutable::create($year, $month, 1)->endOfMonth()->toDateString();

        if (StockLotMonthlyBalance::query()->where('year', $year)->where('month', $month)->whereIn('status', ['confirmed', 'closed'])->exists()) {
            throw InventoryCountException::alreadyClosed();
        }

        return DB::transaction(function () use ($year, $month, $countDate, $note): InventoryCountHeader {
            $header = InventoryCountHeader::query()->firstOrCreate(
                ['year' => $year, 'month' => $month],
                ['count_date' => $countDate, 'status' => 'draft', 'started_at' => now(), 'note' => $note],
            );

            if ($header->status !== 'draft') {
                throw InventoryCountException::notDraft();
            }

            $rows = DB::table('stock_movements as sm')
                ->leftJoin('production_lots as pl', 'pl.id', '=', 'sm.production_lot_id')
                ->whereIn('sm.status', ['confirmed', 'closed'])
                ->whereNull('sm.cancelled_at')
                ->whereDate('sm.movement_date', '<=', $countDate)
                ->groupBy('sm.stock_location_id', 'sm.unit_id', 'sm.production_lot_id', 'pl.lot_code')
                ->orderBy('sm.stock_location_id')->orderBy('sm.production_lot_id')
                ->get([
                    'sm.stock_location_id', 'sm.unit_id', 'sm.production_lot_id',
                    DB::raw('COALESCE(pl.lot_code, MAX(sm.lot_code)) as lot_code'),
                    DB::raw('COALESCE(SUM(sm.quantity), 0) as book_quantity'),
                ]);

            $existing = $header->lines()->get()->keyBy(fn ($line): string => implode(':', [$line->stock_location_id, $line->unit_id, $line->production_lot_id]));
            $lineNo = 1;

            foreach ($rows as $row) {
                $key = implode(':', [$row->stock_location_id, $row->unit_id, $row->production_lot_id]);
                $line = $existing->get($key);
                $values = [
                    'line_no' => $lineNo++, 'stock_location_id' => $row->stock_location_id,
                    'unit_id' => $row->unit_id, 'production_lot_id' => $row->production_lot_id, 'lot_code' => $row->lot_code,
                    'book_quantity' => bcadd((string) $row->book_quantity, '0', 4),
                ];

                if ($line) {
                    $line->update($values);
                } else {
                    $header->lines()->create($values);
                }
            }

            return $header->refresh()->load(['lines.stockLocation', 'lines.unit', 'lines.productionLot']);
        });
    }
}
