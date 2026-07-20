<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\InventoryCountHeader;
use App\Models\InventoryCountLine;
use App\Services\Inventory\ConfirmInventoryCountService;
use App\Services\Inventory\CreateInventoryCountDraftService;
use App\Services\Inventory\SaveInventoryCountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryCountController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['year' => ['nullable', 'integer'], 'month' => ['nullable', 'integer', 'between:1,12']]);
        $query = InventoryCountHeader::query()->withCount(['lines', 'lines as uncounted_count' => fn ($q) => $q->whereNull('counted_quantity'), 'lines as variance_count' => fn ($q) => $q->whereRaw('ABS(COALESCE(variance_quantity, 0)) > 0.00005')])->orderByDesc('year')->orderByDesc('month');
        if (isset($validated['year'])) $query->where('year', $validated['year']);
        if (isset($validated['month'])) $query->where('month', $validated['month']);
        return $this->ok(['inventory_counts' => $query->limit(36)->get()]);
    }

    public function show(InventoryCountHeader $inventoryCount): JsonResponse
    {
        return $this->ok(['inventory_count' => $this->serialize($inventoryCount->load(['lines.stockLocation', 'lines.unit', 'lines.productionLot.capacityUnit']))]);
    }

    public function store(Request $request, CreateInventoryCountDraftService $service): JsonResponse
    {
        $v = $request->validate(['year' => ['required', 'integer', 'between:2000,2100'], 'month' => ['required', 'integer', 'between:1,12'], 'note' => ['nullable', 'string', 'max:1000']]);
        return $this->created(['inventory_count' => $this->serialize($service->create((int) $v['year'], (int) $v['month'], $v['note'] ?? null))]);
    }

    public function update(Request $request, InventoryCountHeader $inventoryCount, SaveInventoryCountService $service): JsonResponse
    {
        $v = $request->validate(['lines' => ['required', 'array'], 'lines.*.id' => ['required', 'integer'], 'lines.*.counted_quantity' => ['nullable', 'numeric', 'min:0'], 'lines.*.reason' => ['nullable', 'string', 'max:1000'], 'lines.*.note' => ['nullable', 'string', 'max:1000']]);
        return $this->ok(['inventory_count' => $this->serialize($service->save($inventoryCount, $v['lines']))]);
    }

    public function confirm(Request $request, InventoryCountHeader $inventoryCount, ConfirmInventoryCountService $service): JsonResponse
    {
        $v = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        return $this->ok(['inventory_count' => $this->serialize($service->confirm($inventoryCount, $v['reason']))]);
    }

    private function serialize(InventoryCountHeader $header): array
    {
        return [
            'id' => $header->id, 'year' => $header->year, 'month' => $header->month, 'count_date' => $header->count_date?->toDateString(),
            'status' => $header->status, 'started_at' => $header->started_at?->toISOString(), 'counted_at' => $header->counted_at?->toISOString(),
            'confirmed_at' => $header->confirmed_at?->toISOString(), 'reason' => $header->reason, 'note' => $header->note,
            'lines' => $header->lines->map(fn (InventoryCountLine $line): array => [
                'id' => $line->id, 'line_no' => $line->line_no,
                'stock_location_id' => $line->stock_location_id, 'stock_location_name' => $line->stockLocation?->name,
                'unit_id' => $line->unit_id, 'unit_name' => $line->unit?->symbol ?? $line->unit?->name,
                'production_lot_id' => $line->production_lot_id, 'lot_code' => $line->productionLot?->lot_code ?? $line->lot_code,
                'lot_name' => $line->productionLot?->display_name,
                'capacity_value' => $line->productionLot?->capacity_value,
                'capacity_unit_name' => $line->productionLot?->capacityUnit?->symbol ?? $line->productionLot?->capacityUnit?->name,
                'book_quantity' => $line->book_quantity, 'counted_quantity' => $line->counted_quantity, 'variance_quantity' => $line->variance_quantity,
                'adjustment_stock_movement_id' => $line->adjustment_stock_movement_id, 'reason' => $line->reason, 'note' => $line->note,
            ])->values()->all(),
        ];
    }
}
