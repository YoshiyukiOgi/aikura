<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\StockMovement;
use App\Services\Inventory\ReverseStockMovementService;
use App\Services\Operations\OperationalPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockMovementController extends ApiController
{
    public function index(Request $request, OperationalPeriod $operationalPeriod): JsonResponse
    {
        $v = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'stock_location_id' => ['nullable', 'integer'],
            'movement_type' => ['nullable', 'string', 'max:80'],
        ]);
        $q = StockMovement::query()->with(['stockLocation', 'unit', 'productionLot'])->orderByDesc('movement_date')->orderByDesc('id');
        $operationalPeriod->applyVisiblePeriod($q, 'movement_date');
        if (isset($v['year'])) $q->whereYear('movement_date', $v['year']);
        if (isset($v['month'])) $q->whereMonth('movement_date', $v['month']);
        if (isset($v['from'])) $q->whereDate('movement_date', '>=', $v['from']);
        if (isset($v['to'])) $q->whereDate('movement_date', '<=', $v['to']);
        if (isset($v['stock_location_id'])) $q->where('stock_location_id', $v['stock_location_id']);
        if (isset($v['movement_type'])) $q->where('movement_type', $v['movement_type']);
        return $this->ok(['stock_movements' => $q->limit(300)->get()->map(fn (StockMovement $m) => $this->serialize($m))->values()->all()]);
    }

    public function correct(Request $request, StockMovement $stockMovement, ReverseStockMovementService $service, OperationalPeriod $operationalPeriod): JsonResponse
    {
        $v = $request->validate(['movement_date' => ['required', 'date'], 'reason' => ['required', 'string', 'max:1000']]);
        $operationalPeriod->ensureOpen($stockMovement->movement_date?->toDateString(), '在庫移動日');
        $operationalPeriod->ensureOpen($v['movement_date'], '訂正日');
        return $this->created(['stock_movement' => $this->serialize($service->reverse($stockMovement, $v['movement_date'], $v['reason'])->load(['stockLocation', 'unit', 'productionLot']))]);
    }

    private function serialize(StockMovement $m): array
    {
        return ['id' => $m->id, 'status' => $m->status, 'movement_type' => $m->movement_type, 'movement_date' => $m->movement_date?->toDateString(),
            'stock_location_id' => $m->stock_location_id, 'stock_location_name' => $m->stockLocation?->name, 'unit_id' => $m->unit_id,
            'unit_name' => $m->unit?->symbol ?? $m->unit?->name, 'quantity' => $m->quantity, 'production_lot_id' => $m->production_lot_id,
            'lot_code' => $m->productionLot?->lot_code ?? $m->lot_code, 'lot_name' => $m->productionLot?->display_name,
            'source_type' => $m->source_type, 'source_document_number' => $m->source_document_number,
            'related_stock_movement_id' => $m->related_stock_movement_id, 'reason' => $m->reason, 'cancelled_at' => $m->cancelled_at?->toISOString()];
    }
}
