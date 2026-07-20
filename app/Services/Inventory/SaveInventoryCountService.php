<?php

namespace App\Services\Inventory;

use App\Exceptions\Inventory\InventoryCountException;
use App\Models\InventoryCountHeader;
use Illuminate\Support\Facades\DB;

class SaveInventoryCountService
{
    /** @param array<int, array{id:int, counted_quantity:string|int|float|null, reason?:string|null, note?:string|null}> $lines */
    public function save(InventoryCountHeader $header, array $lines): InventoryCountHeader
    {
        if ($header->status !== 'draft') throw InventoryCountException::notDraft();

        return DB::transaction(function () use ($header, $lines): InventoryCountHeader {
            $owned = $header->lines()->lockForUpdate()->get()->keyBy('id');
            foreach ($lines as $input) {
                $line = $owned->get((int) $input['id']);
                if (! $line) continue;
                $counted = $input['counted_quantity'];
                $counted = $counted === null || $counted === '' ? null : bcadd((string) $counted, '0', 4);
                $line->update([
                    'counted_quantity' => $counted,
                    'variance_quantity' => $counted === null ? null : bcsub($counted, (string) $line->book_quantity, 4),
                    'counted_at' => $counted === null ? null : now(),
                    'reason' => $input['reason'] ?? null,
                    'note' => $input['note'] ?? null,
                ]);
            }

            $header->update(['counted_at' => $header->lines()->whereNull('counted_quantity')->exists() ? null : now()]);
            return $header->refresh()->load(['lines.stockLocation', 'lines.unit', 'lines.productionLot']);
        });
    }
}
