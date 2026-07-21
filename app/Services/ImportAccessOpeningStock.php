<?php

namespace App\Services;

use App\Models\AccessMigrationBatch;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportAccessOpeningStock
{
    public function __construct(private readonly ExportAccessDetailStockWorkbook $calculator) {}

    /** @return array<string, mixed> */
    public function preview(AccessMigrationBatch $batch, string $asOfDate, string $locationCode = 'main_brewery'): array
    {
        return $this->summary($this->buildPlan($batch, $asOfDate, $locationCode));
    }

    /** @return array<string, mixed> */
    public function import(AccessMigrationBatch $batch, string $asOfDate, string $locationCode = 'main_brewery'): array
    {
        $plan = $this->buildPlan($batch, $asOfDate, $locationCode);

        return DB::transaction(function () use ($plan): array {
            $now = now();
            $documentNumber = $plan['source_document_number'];
            $createdLots = 0;
            $activatedLots = 0;
            $createdMovements = 0;
            $updatedMovements = 0;

            foreach ($plan['rows'] as $row) {
                $product = $plan['products']->get((int) $row->product_id);
                $lot = $this->existingLotForRow($row, $plan['existing_lots']);

                if ($lot === null) {
                    $lot = ProductionLot::query()->create($this->newLotAttributes($row, $product, $plan['location'], $now));
                    $createdLots++;
                } elseif (! $lot->is_active || $lot->status !== 'active') {
                    $lot->update(['status' => 'active', 'is_active' => true, 'disabled_at' => null]);
                    $activatedLots++;
                }

                $key = [
                    'source_type' => 'access_opening_stock',
                    'source_document_number' => $documentNumber,
                    'source_line_no' => $row->source_line_no,
                ];
                $attributes = [
                    'status' => 'confirmed',
                    'movement_type' => 'opening_stock',
                    'movement_date' => $plan['as_of_date'],
                    'stock_location_id' => $plan['location']->id,
                    'unit_id' => $product->inventory_unit_id ?: $product->base_unit_id,
                    'quantity' => bcadd((string) $row->calculated_stock, '0', 4),
                    'production_lot_id' => $lot->id,
                    'lot_code' => $lot->lot_code,
                    'confirmed_at' => $now,
                    'closed_at' => null,
                    'cancelled_at' => null,
                    'cancelled_reason' => null,
                    'reason' => "Access履歴から算出した{$plan['as_of_date']}期首在庫",
                    'note' => "Access移行 batch={$plan['batch_id']}; 商品ID={$row->access_product_id}; 商品詳細ID={$row->access_detail_id}; 基準日={$plan['as_of_date']}",
                ];

                $movement = StockMovement::query()->where($key)->first();
                if ($movement === null) {
                    StockMovement::query()->create($key + $attributes);
                    $createdMovements++;
                } else {
                    $movement->update($attributes);
                    $updatedMovements++;
                }
            }

            return $this->summary($plan) + [
                'committed' => true,
                'lots_created' => $createdLots,
                'lots_activated' => $activatedLots,
                'movements_created' => $createdMovements,
                'movements_updated' => $updatedMovements,
            ];
        }, 3);
    }

    /** @return array<string, mixed> */
    private function buildPlan(AccessMigrationBatch $batch, string $asOfDate, string $locationCode): array
    {
        if ($batch->status !== 'completed') {
            throw new RuntimeException("完了済みのAccess移行バッチだけを期首在庫へ取り込めます: {$batch->status}");
        }

        $asOfDate = CarbonImmutable::parse($asOfDate)->toDateString();
        $location = StockLocation::query()
            ->where('code', $locationCode)
            ->where('is_inventory_managed', true)
            ->first() ?? throw new RuntimeException("在庫管理対象の在庫場所を解決できません: {$locationCode}");
        $allRows = collect($this->calculator->calculateRows($batch, $asOfDate));
        $rows = $allRows
            ->values()
            ->map(function (object $row, int $index): object {
                $row->source_line_no = $index + 1;

                return $row;
            })
            ->filter(fn (object $row): bool => bccomp(bcadd((string) $row->calculated_stock, '0', 4), '0.0000', 4) !== 0)
            ->values();

        $duplicateDetails = $rows->groupBy(fn (object $row): string => (string) $row->access_detail_id)
            ->filter(fn ($group): bool => $group->pluck('access_product_id')->unique()->count() > 1)
            ->keys();

        $missingProducts = $rows->filter(fn (object $row): bool => $row->product_id === null)->pluck('access_product_id')->unique();
        if ($missingProducts->isNotEmpty()) {
            throw new RuntimeException('商品マッピングのない在庫があります: '.$missingProducts->take(10)->implode(', '));
        }

        $products = Product::query()->whereIn('id', $rows->pluck('product_id')->unique())->get()->keyBy('id');
        $invalidUnits = $products->filter(fn (Product $product): bool => ! $product->inventory_unit_id && ! $product->base_unit_id);
        if ($invalidUnits->isNotEmpty()) {
            throw new RuntimeException('在庫単位のない商品があります: '.$invalidUnits->pluck('product_code')->implode(', '));
        }

        $candidateExternalCodes = $rows->map(fn (object $row): string => $this->canonicalExternalCode($row))->unique();
        $existingLots = ProductionLot::query()
            ->whereIn('external_system_code', $candidateExternalCodes)
            ->get()
            ->keyBy('external_system_code');
        $duplicateLots = ProductionLot::query()
            ->whereIn('external_system_code', $candidateExternalCodes)
            ->selectRaw('external_system_code, COUNT(*) as aggregate')
            ->groupBy('external_system_code')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('external_system_code');
        if ($duplicateLots->isNotEmpty()) {
            throw new RuntimeException('商品詳細に対応する既存ロットが重複しています: '.$duplicateLots->take(10)->implode(', '));
        }
        $documentNumber = "ITARO-OPENING-BATCH-{$batch->id}-{$asOfDate}";
        $duplicateMovements = StockMovement::query()
            ->where('source_type', 'access_opening_stock')
            ->where('source_document_number', $documentNumber)
            ->selectRaw('source_line_no, COUNT(*) as aggregate')
            ->groupBy('source_line_no')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
        if ($duplicateMovements) {
            throw new RuntimeException('既存の期首在庫に重複行があります。登録を中止しました。');
        }

        return [
            'batch_id' => $batch->id,
            'as_of_date' => $asOfDate,
            'source_document_number' => $documentNumber,
            'location' => $location,
            'rows' => $rows,
            'products' => $products,
            'existing_lots' => $existingLots,
            'duplicate_detail_ids' => $duplicateDetails,
        ];
    }

    /** @return array<string, mixed> */
    private function summary(array $plan): array
    {
        $rows = $plan['rows'];

        return [
            'batch_id' => $plan['batch_id'],
            'as_of_date' => $plan['as_of_date'],
            'location_code' => $plan['location']->code,
            'source_document_number' => $plan['source_document_number'],
            'row_count' => $rows->count(),
            'positive_count' => $rows->filter(fn (object $row): bool => (float) $row->calculated_stock > 0)->count(),
            'negative_count' => $rows->filter(fn (object $row): bool => (float) $row->calculated_stock < 0)->count(),
            'quantity_total' => bcadd((string) $rows->sum(fn (object $row): float => (float) $row->calculated_stock), '0', 4),
            'missing_lot_count' => $rows->filter(fn (object $row): bool => $this->existingLotForRow($row, $plan['existing_lots']) === null)->count(),
            'inactive_lot_count' => $rows->filter(function (object $row) use ($plan): bool {
                $lot = $this->existingLotForRow($row, $plan['existing_lots']);

                return $lot !== null && (! $lot->is_active || $lot->status !== 'active');
            })->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function newLotAttributes(object $row, Product $product, StockLocation $location, mixed $now): array
    {
        $detailId = (string) $row->access_detail_id;
        $lotCode = 'ITARO-LOT-'.$row->access_product_id.'-'.str_pad($detailId, 6, '0', STR_PAD_LEFT);
        $detailName = $row->detail_name ?: "名称未登録 詳細ID {$detailId}";

        return [
            'lot_code' => $lotCode,
            'display_name' => mb_substr("{$product->display_name} / {$detailName}", 0, 160),
            'status' => 'active',
            'stock_location_id' => $location->id,
            'unit_id' => $product->inventory_unit_id ?: $product->base_unit_id,
            'capacity_value' => $product->capacity_value,
            'capacity_unit_id' => $product->capacity_unit_id,
            'alcohol_percentage' => $product->alcohol_percentage,
            'analysis_status' => $product->is_alcohol ? 'confirmed' : null,
            'external_system_code' => $this->canonicalExternalCode($row),
            'legacy_lot_text' => mb_substr($detailName, 0, 160),
            'search_key' => "{$lotCode} {$product->product_code} {$product->display_name} {$detailName}",
            'note' => "Access期首在庫補完ロット; 商品詳細ID={$detailId}; 商品ID={$row->access_product_id}",
            'is_active' => true,
            'disabled_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function existingLotForRow(object $row, $existingLots): ?ProductionLot
    {
        return $existingLots->get($this->canonicalExternalCode($row));
    }

    private function canonicalExternalCode(object $row): string
    {
        return 'ITARO-PRODUCT-DETAIL-'.$row->access_product_id.'-'.$row->access_detail_id;
    }
}
