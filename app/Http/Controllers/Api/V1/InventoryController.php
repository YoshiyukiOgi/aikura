<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\AllocateInventoryLotRequest;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\ShipmentLine;
use App\Models\ShipmentLotAllocation;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Services\Inventory\LotStockBalance;
use App\Services\Inventory\LotStockBalanceService;
use App\Services\Inventory\LotVisibilityPolicy;
use App\Services\Inventory\ProductLotCandidateSummaryService;
use App\Services\Operations\OperationalPeriod;
use App\Services\Shipment\AllocateShipmentLineLotService;
use App\Support\SearchTextNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends ApiController
{
    /** @var array<int, Product|null> */
    private array $lotProductCache = [];

    public function stock(Request $request, LotStockBalanceService $service, LotVisibilityPolicy $visibility, OperationalPeriod $operationalPeriod): JsonResponse
    {
        $validated = $request->validate([
            'stock_location_id' => ['nullable', 'integer', 'exists:stock_locations,id'],
            'as_of_date' => ['nullable', 'date'],
            'include_zero_stock' => ['nullable', 'boolean'],
            'product_type' => ['nullable', 'in:sake,kasu,food,goods'],
        ]);
        $asOfDate = $validated['as_of_date'] ?? now()->toDateString();
        $operationalPeriod->ensureOpen($asOfDate, '在庫基準日');
        $includeZeroStock = $visibility->includeZeroStock((bool) ($validated['include_zero_stock'] ?? false));
        $balances = $service->allAsOf($asOfDate, $includeZeroStock)
            ->filter(fn (LotStockBalance $balance): bool => $visibility->shouldDisplayBalance($balance, $includeZeroStock));
        if (isset($validated['stock_location_id'])) {
            $balances = $balances->where('stockLocationId', (int) $validated['stock_location_id']);
        }
        if (isset($validated['product_type'])) {
            $balances = $balances->filter(fn (LotStockBalance $balance): bool => $this->lotStockBalanceMatchesProductType($balance, $validated['product_type']));
        }

        return $this->ok([
            'inventory_basis' => 'production_lot',
            'as_of_date' => $asOfDate,
            'zero_stock_hidden' => ! $includeZeroStock,
            'stock_balances' => $this->sortLotStockRows($balances
                ->map(fn (LotStockBalance $balance): array => $this->serializeLotStockBalance($balance)))
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
            'product_type' => ['nullable', 'in:sake,kasu,food,goods'],
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
                ->when(isset($validated['product_type']), fn ($balances) => $balances->filter(
                    fn (LotStockBalance $balance): bool => $this->lotStockBalanceMatchesProductType($balance, $validated['product_type'])
                ))
                ->map(fn (LotStockBalance $balance): array => $this->serializeLotStockBalance($balance))
                ->values()
                ->all(),
        ]);
    }

    public function lotStockAsOf(Request $request, LotStockBalanceService $service, LotVisibilityPolicy $visibility, OperationalPeriod $operationalPeriod): JsonResponse
    {
        $validated = $request->validate([
            'as_of_date' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:160'],
            'include_zero_stock' => ['nullable', 'boolean'],
            'product_type' => ['nullable', 'in:sake,kasu,food,goods'],
        ]);

        $asOfDate = $validated['as_of_date'] ?? now()->toDateString();
        $operationalPeriod->ensureOpen($asOfDate, '在庫基準日');
        $terms = SearchTextNormalizer::searchTerms($validated['q'] ?? null);
        $includeZeroStock = $visibility->includeZeroStock((bool) ($validated['include_zero_stock'] ?? false));

        $rows = $this->sortLotStockRows($service->allAsOf($asOfDate, $includeZeroStock)
            ->map(function (LotStockBalance $balance) use ($asOfDate): array {
                $row = $this->serializeLotStockBalance($balance);
                $row['latest_movement_date'] = $this->latestMovementDate($balance, $asOfDate);

                return $row;
            })
            ->filter(fn (array $row): bool => ! isset($validated['product_type']) || $row['product_type'] === $validated['product_type'])
            ->filter(function (array $row) use ($terms): bool {
                if ($terms === []) {
                    return true;
                }

                $searchKey = $this->lotStockSearchKey($row);

                if ($searchKey === '') {
                    return false;
                }

                foreach ($terms as $term) {
                    if (! str_contains($searchKey, $term)) {
                        return false;
                    }
                }

                return true;
            }))
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
        OperationalPeriod $operationalPeriod,
    ): JsonResponse {
        $validated = $request->validated();
        $shipmentLine = ShipmentLine::query()->with('shipmentHeader')->findOrFail((int) $validated['shipment_line_id']);
        $operationalPeriod->ensureOpen($shipmentLine->shipmentHeader?->document_date?->toDateString(), '出荷日');

        $allocation = $service->allocate(
            shipmentLine: $shipmentLine,
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
        $product = $this->productForLot($lot);
        $stockLocation = StockLocation::query()->find($balance->stockLocationId);

        return [
            'production_lot_id' => $balance->productionLotId,
            'stock_location_id' => $balance->stockLocationId,
            'unit_id' => $balance->unitId,
            'product_code' => $product?->product_code,
            'product_name' => $product?->display_name,
            'sort_product_name' => $product?->name ?? $product?->display_name,
            'product_type' => $product?->product_type,
            'product_type_label' => $this->productTypeLabel($product?->product_type),
            'physical_quantity' => $balance->physicalQuantity,
            'reserved_quantity' => $balance->reservedQuantity,
            'allocated_quantity' => $balance->allocatedQuantity,
            'available_quantity' => $balance->availableQuantity,
            'lot_code' => $lot?->lot_code,
            'lot_name' => $lot?->display_name,
            'legacy_lot_text' => $lot?->legacy_lot_text,
            'capacity_value' => $lot?->capacity_value,
            'capacity_unit_name' => $lot?->capacityUnit?->symbol ?: $lot?->capacityUnit?->name,
            'production_date' => $lot?->production_date?->toDateString(),
            'bottling_date' => $lot?->bottling_date?->toDateString(),
            'stock_location_code' => $stockLocation?->code,
            'stock_location_name' => $stockLocation?->name,
            'alcohol_percentage' => $lot?->alcohol_percentage,
            'sake_meter_value' => $lot?->sake_meter_value,
            'acidity' => $lot?->acidity,
            'amino_acidity' => $lot?->amino_acidity,
            'analysis_status' => $lot?->analysis_status,
            'unit_name' => $lot?->unit?->symbol ?: $lot?->unit?->name,
        ];
    }

    private function latestMovementDate(LotStockBalance $balance, string $asOfDate): ?string
    {
        return StockMovement::query()
            ->where('production_lot_id', $balance->productionLotId)
            ->where('stock_location_id', $balance->stockLocationId)
            ->where('unit_id', $balance->unitId)
            ->whereIn('status', ['confirmed', 'closed'])
            ->whereNull('cancelled_at')
            ->whereDate('movement_date', '<=', $asOfDate)
            ->max('movement_date');
    }

    private function lotStockBalanceMatchesProductType(LotStockBalance $balance, string $productType): bool
    {
        $lot = ProductionLot::query()->find($balance->productionLotId);

        return $this->productForLot($lot)?->product_type === $productType;
    }

    private function productForLot(?ProductionLot $lot): ?Product
    {
        if ($lot === null) {
            return null;
        }

        if (array_key_exists($lot->id, $this->lotProductCache)) {
            return $this->lotProductCache[$lot->id];
        }

        return $this->lotProductCache[$lot->id] = $this->productForLotAttributes($lot->external_system_code, $lot->note);
    }

    private function sortLotStockRows($rows)
    {
        $productTypeRank = ['sake' => 1, 'kasu' => 2, 'food' => 3, 'goods' => 4];

        return $rows->sort(function (array $a, array $b) use ($productTypeRank): int {
            $aKeys = [
                $productTypeRank[$a['product_type'] ?? ''] ?? 99,
                (string) ($a['lot_name'] ?? $a['sort_product_name'] ?? $a['product_name'] ?? ''),
                $a['capacity_value'] === null ? PHP_FLOAT_MAX : (float) $a['capacity_value'],
                (string) ($a['production_date'] ?? $a['bottling_date'] ?? '9999-12-31'),
                (string) ($a['lot_code'] ?? ''),
                (string) ($a['stock_location_code'] ?? $a['stock_location_name'] ?? $a['stock_location_id'] ?? ''),
            ];
            $bKeys = [
                $productTypeRank[$b['product_type'] ?? ''] ?? 99,
                (string) ($b['lot_name'] ?? $b['sort_product_name'] ?? $b['product_name'] ?? ''),
                $b['capacity_value'] === null ? PHP_FLOAT_MAX : (float) $b['capacity_value'],
                (string) ($b['production_date'] ?? $b['bottling_date'] ?? '9999-12-31'),
                (string) ($b['lot_code'] ?? ''),
                (string) ($b['stock_location_code'] ?? $b['stock_location_name'] ?? $b['stock_location_id'] ?? ''),
            ];

            return $aKeys <=> $bKeys;
        });
    }

    private function productForLotAttributes(?string $externalSystemCode, ?string $note): ?Product
    {
        $legacyProductId = null;
        if (is_string($externalSystemCode) && preg_match('/^ITARO-PRODUCT-DETAIL-(\d+)-\d+$/', $externalSystemCode, $matches)) {
            $legacyProductId = $matches[1];
        } elseif (is_string($externalSystemCode) && preg_match('/^migration-xlsx:(\d+):\d+$/', $externalSystemCode, $matches)) {
            $legacyProductId = $matches[1];
        } elseif (is_string($note) && preg_match('/商品ID=(\d+)/u', $note, $matches)) {
            $legacyProductId = $matches[1];
        } elseif (is_string($note) && preg_match('/旧商品ID(\d+)/u', $note, $matches)) {
            $legacyProductId = $matches[1];
        }

        return $legacyProductId === null
            ? null
            : Product::query()->where('legacy_code', $legacyProductId)->first();
    }

    private function productTypeLabel(?string $productType): ?string
    {
        return match ($productType) {
            'sake' => '酒',
            'kasu' => '酒粕',
            'food' => '食品',
            'goods' => 'グッズ・その他',
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $row
     */
    private function lotStockSearchKey(array $row): string
    {
        return SearchTextNormalizer::searchKey(
            $row['product_code'] ?? null,
            $row['product_name'] ?? null,
            $row['lot_code'] ?? null,
            $row['lot_name'] ?? null,
            $row['legacy_lot_text'] ?? null,
            isset($row['capacity_value']) ? (string) $row['capacity_value'] : null,
            $row['capacity_unit_name'] ?? null,
        );
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
