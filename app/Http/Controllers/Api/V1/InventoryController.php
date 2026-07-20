<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\AllocateInventoryLotRequest;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\ShipmentLine;
use App\Models\ShipmentLotAllocation;
use App\Models\StockLocation;
use App\Services\Inventory\LotStockBalance;
use App\Services\Inventory\LotStockBalanceService;
use App\Services\Inventory\LotVisibilityPolicy;
use App\Services\Inventory\ProductLotCandidateSummaryService;
use App\Services\Shipment\AllocateShipmentLineLotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends ApiController
{
    public function stock(Request $request, LotStockBalanceService $service, LotVisibilityPolicy $visibility): JsonResponse
    {
        $validated = $request->validate([
            'stock_location_id' => ['nullable', 'integer', 'exists:stock_locations,id'],
            'as_of_date' => ['nullable', 'date'],
            'include_zero_stock' => ['nullable', 'boolean'],
        ]);
        $asOfDate = $validated['as_of_date'] ?? now()->toDateString();
        $includeZeroStock = $visibility->includeZeroStock((bool) ($validated['include_zero_stock'] ?? false));
        $balances = $service->allAsOf($asOfDate, $includeZeroStock)
            ->filter(fn (LotStockBalance $balance): bool => $visibility->shouldDisplayBalance($balance, $includeZeroStock));
        if (isset($validated['stock_location_id'])) {
            $balances = $balances->where('stockLocationId', (int) $validated['stock_location_id']);
        }

        return $this->ok([
            'inventory_basis' => 'production_lot',
            'as_of_date' => $asOfDate,
            'zero_stock_hidden' => ! $includeZeroStock,
            'stock_balances' => $balances
                ->map(fn (LotStockBalance $balance): array => $this->serializeLotStockBalance($balance))
                ->values()
                ->all(),
        ]);
    }

    public function lots(Request $request, LotStockBalanceService $service, LotVisibilityPolicy $visibility): JsonResponse
    {
        $validated = $request->validate([
            'include_inactive' => ['nullable', 'boolean'],
            'include_zero_stock' => ['nullable', 'boolean'],
        ]);
        $includeZeroStock = $visibility->includeZeroStock((bool) ($validated['include_zero_stock'] ?? false));
        $query = ProductionLot::query()->with(['unit', 'capacityUnit']);
        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        if (! $includeZeroStock) {
            $visibleLotIds = $service->all()
                ->filter(fn (LotStockBalance $balance): bool => $visibility->shouldDisplayBalance($balance, false))
                ->pluck('productionLotId')
                ->unique()
                ->all();
            $query->whereIn('id', $visibleLotIds);
        }

        return $this->ok([
            'zero_stock_hidden' => ! $includeZeroStock,
            'production_lots' => $query->orderByDesc('production_date')->orderBy('lot_code')->get()->map(fn (ProductionLot $lot): array => [
                'id' => $lot->id,
                'lot_code' => $lot->lot_code,
                'display_name' => $lot->display_name,
                'unit_id' => $lot->unit_id,
                'unit_name' => $lot->unit?->symbol ?: $lot->unit?->name,
                'capacity_value' => $lot->capacity_value,
                'capacity_unit_id' => $lot->capacity_unit_id,
                'capacity_unit_name' => $lot->capacityUnit?->symbol ?: $lot->capacityUnit?->name,
                'alcohol_percentage' => $lot->alcohol_percentage,
                'sake_meter_value' => $lot->sake_meter_value,
                'acidity' => $lot->acidity,
                'amino_acidity' => $lot->amino_acidity,
                'analysis_date' => $lot->analysis_date?->toDateString(),
                'analysis_status' => $lot->analysis_status,
            ])->values(),
        ]);
    }

    public function productLotCandidateSummary(Request $request, Product $product, ProductLotCandidateSummaryService $service): JsonResponse
    {
        $validated = $request->validate(['stock_location_id' => ['nullable', 'integer', 'exists:stock_locations,id']]);

        return $this->ok([
            'product_id' => $product->id,
            'basis' => 'dynamic_lot_candidates',
            'candidate_availability' => $service->forProduct($product, isset($validated['stock_location_id']) ? (int) $validated['stock_location_id'] : null),
        ]);
    }

    public function updateLot(Request $request, ProductionLot $productionLot): JsonResponse
    {
        $validated = $request->validate([
            'alcohol_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sake_meter_value' => ['nullable', 'numeric', 'min:-100', 'max:100'],
            'acidity' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'amino_acidity' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'analysis_date' => ['nullable', 'date'],
            'analysis_status' => ['nullable', 'in:provisional,confirmed'],
        ]);
        $productionLot->update($validated);

        return $this->ok(['production_lot' => $productionLot->refresh()]);
    }

    public function lotStock(Request $request, LotStockBalanceService $service, LotVisibilityPolicy $visibility): JsonResponse
    {
        $validated = $request->validate([
            'production_lot_id' => ['nullable', 'integer', 'exists:production_lots,id'],
            'stock_location_id' => ['nullable', 'integer', 'exists:stock_locations,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'include_zero_stock' => ['nullable', 'boolean'],
        ]);

        if (isset($validated['production_lot_id'], $validated['stock_location_id'], $validated['unit_id'])) {
            return $this->ok([
                'lot_stock_balance' => $this->serializeLotStockBalance($service->forLotLocationUnit(
                    (int) $validated['production_lot_id'],
                    (int) $validated['stock_location_id'],
                    (int) $validated['unit_id'],
                )),
            ]);
        }

        $includeZeroStock = $visibility->includeZeroStock((bool) ($validated['include_zero_stock'] ?? false));

        return $this->ok([
            'zero_stock_hidden' => ! $includeZeroStock,
            'lot_stock_balances' => $service->all()
                ->filter(fn (LotStockBalance $balance): bool => $visibility->shouldDisplayBalance($balance, $includeZeroStock))
                ->map(fn (LotStockBalance $balance): array => $this->serializeLotStockBalance($balance))
                ->values()
                ->all(),
        ]);
    }

    public function lotStockAsOf(Request $request, LotVisibilityPolicy $visibility): JsonResponse
    {
        $validated = $request->validate([
            'as_of_date' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:160'],
            'include_zero_stock' => ['nullable', 'boolean'],
        ]);

        $asOfDate = $validated['as_of_date'] ?? now()->toDateString();
        $keyword = trim((string) ($validated['q'] ?? ''));
        $includeZeroStock = $visibility->includeZeroStock((bool) ($validated['include_zero_stock'] ?? false));

        $query = DB::table('stock_movements as sm')
            ->join('production_lots as pl', 'pl.id', '=', 'sm.production_lot_id')
            ->join('stock_locations as sl', 'sl.id', '=', 'sm.stock_location_id')
            ->join('units as u', 'u.id', '=', 'sm.unit_id')
            ->whereNotNull('sm.production_lot_id')
            ->whereIn('sm.status', ['confirmed', 'closed'])
            ->whereNull('sm.cancelled_at')
            ->whereDate('sm.movement_date', '<=', $asOfDate);

        if ($keyword !== '') {
            $like = '%'.$keyword.'%';
            $query->where(function ($query) use ($like): void {
                $query->where('pl.lot_code', 'like', $like)
                    ->orWhere('pl.display_name', 'like', $like)
                    ->orWhere('pl.legacy_lot_text', 'like', $like);
            });
        }

        $rowsQuery = $query
            ->selectRaw('
                sm.production_lot_id,
                sm.stock_location_id,
                sm.unit_id,
                pl.lot_code,
                pl.display_name as lot_name,
                pl.production_date,
                pl.bottling_date,
                sl.code as stock_location_code,
                sl.name as stock_location_name,
                COALESCE(u.symbol, u.name, u.code) as unit_name,
                COALESCE(SUM(sm.quantity), 0) as physical_quantity,
                MAX(sm.movement_date) as latest_movement_date
            ')
            ->groupBy(
                'sm.production_lot_id',
                'sm.stock_location_id',
                'sm.unit_id',
                'pl.lot_code',
                'pl.display_name',
                'pl.production_date',
                'pl.bottling_date',
                'sl.code',
                'sl.name',
                'u.symbol',
                'u.name',
                'u.code',
            );

        if (! $includeZeroStock) {
            $rowsQuery->havingRaw('ABS(SUM(sm.quantity)) > 0.00005');
        }

        $rows = $rowsQuery
            ->orderBy('pl.lot_code')
            ->orderBy('sl.code')
            ->get()
            ->map(fn (object $row): array => [
                'production_lot_id' => (int) $row->production_lot_id,
                'stock_location_id' => (int) $row->stock_location_id,
                'unit_id' => (int) $row->unit_id,
                'lot_code' => $row->lot_code,
                'lot_name' => $row->lot_name,
                'production_date' => $row->production_date,
                'bottling_date' => $row->bottling_date,
                'stock_location_code' => $row->stock_location_code,
                'stock_location_name' => $row->stock_location_name,
                'unit_name' => $row->unit_name,
                'physical_quantity' => bcadd((string) $row->physical_quantity, '0', 4),
                'latest_movement_date' => $row->latest_movement_date,
            ])
            ->values()
            ->all();

        return $this->ok([
            'as_of_date' => $asOfDate,
            'zero_stock_hidden' => ! $includeZeroStock,
            'lot_stock_balances' => $rows,
        ]);
    }

    public function allocate(
        AllocateInventoryLotRequest $request,
        AllocateShipmentLineLotService $service,
    ): JsonResponse {
        $validated = $request->validated();

        $allocation = $service->allocate(
            shipmentLine: ShipmentLine::findOrFail((int) $validated['shipment_line_id']),
            productionLot: ProductionLot::findOrFail((int) $validated['production_lot_id']),
            stockLocation: StockLocation::findOrFail((int) $validated['stock_location_id']),
            quantity: $validated['quantity'],
            reason: $validated['reason'] ?? null,
        );

        return $this->created([
            'allocation' => $this->serializeAllocation($allocation),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLotStockBalance(LotStockBalance $balance): array
    {
        $lot = ProductionLot::query()->with(['unit', 'capacityUnit'])->find($balance->productionLotId);

        return [
            'production_lot_id' => $balance->productionLotId,
            'stock_location_id' => $balance->stockLocationId,
            'unit_id' => $balance->unitId,
            'physical_quantity' => $balance->physicalQuantity,
            'reserved_quantity' => $balance->reservedQuantity,
            'allocated_quantity' => $balance->allocatedQuantity,
            'available_quantity' => $balance->availableQuantity,
            'lot_code' => $lot?->lot_code,
            'lot_name' => $lot?->display_name,
            'capacity_value' => $lot?->capacity_value,
            'capacity_unit_name' => $lot?->capacityUnit?->symbol ?: $lot?->capacityUnit?->name,
            'alcohol_percentage' => $lot?->alcohol_percentage,
            'sake_meter_value' => $lot?->sake_meter_value,
            'acidity' => $lot?->acidity,
            'amino_acidity' => $lot?->amino_acidity,
            'analysis_status' => $lot?->analysis_status,
            'unit_name' => $lot?->unit?->symbol ?: $lot?->unit?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAllocation(ShipmentLotAllocation $allocation): array
    {
        return [
            'id' => $allocation->id,
            'status' => $allocation->status,
            'shipment_header_id' => $allocation->shipment_header_id,
            'shipment_line_id' => $allocation->shipment_line_id,
            'product_id' => $allocation->product_id,
            'production_lot_id' => $allocation->production_lot_id,
            'stock_location_id' => $allocation->stock_location_id,
            'unit_id' => $allocation->unit_id,
            'quantity' => $allocation->quantity,
            'allocated_at' => $allocation->allocated_at?->toISOString(),
            'reason' => $allocation->reason,
        ];
    }
}
