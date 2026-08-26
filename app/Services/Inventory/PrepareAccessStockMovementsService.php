<?php

namespace App\Services\Inventory;

use App\Models\AccessMigrationBatch;
use App\Models\ProductionLot;
use App\Models\StockMovement;
use App\Services\ExportAccessDetailStockWorkbook;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PrepareAccessStockMovementsService
{
    public function __construct(private readonly ExportAccessDetailStockWorkbook $calculator) {}

    /** @return array<string, mixed> */
    public function preview(AccessMigrationBatch $batch, int $year, int $month): array
    {
        [$start, $end] = $this->period($year, $month);
        $shipments = $this->shipmentRows($start, $end);
        $operations = $this->operationRows($start, $end);
        $this->resolveLots($shipments, $operations);

        return [
            'shipment_movement_count' => $shipments->count(),
            'shipment_movement_quantity' => $this->sum($shipments, fn (object $row): string => bcmul((string) $row->quantity, '-1', 4)),
            'non_sales_movement_count' => $operations->count(),
            'non_sales_movement_quantity' => $this->sum($operations, fn (object $row): string => (string) $row->quantity),
            'existing_shipment_movements' => StockMovement::query()->whereIn('source_shipment_line_id', $shipments->pluck('line_id'))->count(),
            'existing_non_sales_movements' => DB::table('non_sales_stock_operation_lines')->whereIn('id', $operations->pluck('line_id'))->whereNotNull('stock_movement_id')->count(),
            'expected_stock' => $this->expectedStock($batch, $end),
        ];
    }

    /** @return array<string, mixed> */
    public function apply(AccessMigrationBatch $batch, int $year, int $month): array
    {
        [$start, $end] = $this->period($year, $month);
        $shipments = $this->shipmentRows($start, $end);
        $operations = $this->operationRows($start, $end);
        $lots = $this->resolveLots($shipments, $operations);

        return DB::transaction(function () use ($batch, $year, $month, $start, $end, $shipments, $operations, $lots): array {
            $createdShipments = 0;
            $updatedShipments = 0;
            foreach ($shipments as $row) {
                $existing = StockMovement::query()->where('source_shipment_line_id', $row->line_id)->lockForUpdate()->get();
                if ($existing->count() > 1) {
                    throw new RuntimeException("出荷明細{$row->line_id}の在庫移動が重複しています。");
                }
                $lot = $lots[$this->lotKey($row)];
                $attributes = [
                    'status' => 'confirmed',
                    'movement_type' => 'shipment',
                    'movement_date' => $row->movement_date,
                    'stock_location_id' => $lot->stock_location_id,
                    'unit_id' => $lot->unit_id,
                    'quantity' => bcmul((string) $row->quantity, '-1', 4),
                    'source_type' => 'shipment',
                    'source_document_number' => $row->document_number,
                    'source_line_no' => $row->line_no,
                    'source_shipment_header_id' => $row->header_id,
                    'production_lot_id' => $lot->id,
                    'lot_code' => $lot->lot_code,
                    'confirmed_at' => now(),
                    'closed_at' => null,
                    'cancelled_at' => null,
                    'cancelled_reason' => null,
                    'reason' => 'Access 2026年7月出荷の締め準備',
                    'note' => "Access出荷伝票={$row->legacy_document_number}; 商品詳細ID={$row->detail_id}",
                ];
                if ($existing->isEmpty()) {
                    StockMovement::query()->create(['source_shipment_line_id' => $row->line_id] + $attributes);
                    $createdShipments++;
                } else {
                    $existing->first()->update($attributes);
                    $updatedShipments++;
                }
            }

            $createdOperations = 0;
            $updatedOperations = 0;
            foreach ($operations as $row) {
                $lot = $lots[$this->lotKey($row)];
                $movement = $row->stock_movement_id === null
                    ? StockMovement::query()
                        ->where('source_type', 'non_sales_stock_operation')
                        ->where('source_document_number', $row->operation_number)
                        ->where('source_line_no', $row->line_no)
                        ->lockForUpdate()
                        ->first()
                    : StockMovement::query()->lockForUpdate()->find($row->stock_movement_id);
                $attributes = [
                    'status' => 'confirmed',
                    'movement_type' => 'non_sales_'.$row->operation_type,
                    'movement_date' => $row->movement_date,
                    'stock_location_id' => $lot->stock_location_id,
                    'unit_id' => $lot->unit_id,
                    'quantity' => bcadd((string) $row->quantity, '0', 4),
                    'source_type' => 'non_sales_stock_operation',
                    'source_document_number' => $row->operation_number,
                    'source_line_no' => $row->line_no,
                    'production_lot_id' => $lot->id,
                    'lot_code' => $lot->lot_code,
                    'confirmed_at' => now(),
                    'closed_at' => null,
                    'cancelled_at' => null,
                    'cancelled_reason' => null,
                    'reason' => $row->reason,
                    'note' => "Access販売外出入={$row->legacy_operation_id}; 商品詳細ID={$row->detail_id}",
                ];
                if ($movement === null) {
                    $movement = StockMovement::query()->create($attributes);
                    $createdOperations++;
                } else {
                    $movement->update($attributes);
                    $updatedOperations++;
                }
                DB::table('non_sales_stock_operation_lines')->where('id', $row->line_id)->update([
                    'stock_movement_id' => $movement->id,
                    'production_lot_id' => $lot->id,
                    'lot_code' => $lot->lot_code,
                    'stock_location_id' => $lot->stock_location_id,
                    'unit_id' => $lot->unit_id,
                    'updated_at' => now(),
                ]);
            }

            $reconciliation = $this->reconcile($batch, $start, $end);
            if ($reconciliation['difference_count'] !== 0) {
                throw new RuntimeException('7月末在庫がAccess計算値と一致しないため、在庫移動をロールバックしました。');
            }

            return [
                'year' => $year,
                'month' => $month,
                'shipment_movements_created' => $createdShipments,
                'shipment_movements_updated' => $updatedShipments,
                'non_sales_movements_created' => $createdOperations,
                'non_sales_movements_updated' => $updatedOperations,
                'reconciliation' => $reconciliation,
            ];
        }, 3);
    }

    /** @return array<string, mixed> */
    public function reconcile(AccessMigrationBatch $batch, string $start, string $end): array
    {
        $expected = $this->expectedStockMap($batch, $end);
        $actual = [];
        $movements = DB::table('stock_movements as sm')
            ->join('production_lots as pl', 'pl.id', '=', 'sm.production_lot_id')
            ->leftJoin('shipment_lines as sl', 'sl.id', '=', 'sm.source_shipment_line_id')
            ->leftJoin('products as sp', 'sp.id', '=', 'sl.product_id')
            ->leftJoin('non_sales_stock_operation_lines as nl', 'nl.stock_movement_id', '=', 'sm.id')
            ->leftJoin('products as np', 'np.id', '=', 'nl.product_id')
            ->whereIn('sm.status', ['confirmed', 'closed'])
            ->whereNull('sm.cancelled_at')
            ->whereBetween('sm.movement_date', [$start, $end])
            ->get(['sm.quantity', 'pl.external_system_code', DB::raw('COALESCE(sp.legacy_code, np.legacy_code) AS legacy_code')]);
        foreach ($movements as $movement) {
            $external = (string) $movement->external_system_code;
            if (preg_match('/^ITARO-PRODUCT-DETAIL-([^-]+)-(.+)$/', $external, $matches)) {
                $key = $matches[1].'|'.$matches[2];
            } elseif (preg_match('/^ITARO-DETAIL-(.+)$/', $external, $matches) && $movement->legacy_code !== null) {
                $key = $movement->legacy_code.'|'.$matches[1];
            } else {
                continue;
            }
            $actual[$key] = bcadd($actual[$key] ?? '0.0000', (string) $movement->quantity, 4);
        }
        $actual = array_filter($actual, fn (string $quantity): bool => bccomp($quantity, '0.0000', 4) !== 0);

        $differences = [];
        foreach (array_unique(array_merge(array_keys($expected), array_keys($actual))) as $key) {
            $expectedQuantity = $expected[$key] ?? '0.0000';
            $actualQuantity = $actual[$key] ?? '0.0000';
            if (bccomp($expectedQuantity, $actualQuantity, 4) !== 0) {
                $differences[] = ['key' => $key, 'expected' => $expectedQuantity, 'actual' => $actualQuantity];
            }
        }

        return [
            'expected_count' => count($expected),
            'actual_count' => count($actual),
            'expected_total' => $this->sumArray($expected),
            'actual_total' => $this->sumArray($actual),
            'difference_count' => count($differences),
            'differences' => array_slice($differences, 0, 20),
        ];
    }

    /** @return array{0:string,1:string} */
    private function period(int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1);

        return [$start->toDateString(), $start->endOfMonth()->toDateString()];
    }

    private function shipmentRows(string $start, string $end): Collection
    {
        return DB::table('shipment_lines as sl')
            ->join('shipment_headers as sh', 'sh.id', '=', 'sl.shipment_header_id')
            ->join('products as p', 'p.id', '=', 'sl.product_id')
            ->join('settlement_receivable_categories as sr', 'sr.id', '=', 'sh.settlement_receivable_category_id')
            ->where('sh.status', 'confirmed')
            ->where('p.product_type', 'sake')
            ->where('p.is_inventory_managed', true)
            ->where('sr.reduces_stock', true)
            ->where('sl.confirmed_quantity', '!=', 0)
            ->whereBetween('sh.actual_shipment_date', [$start, $end])
            ->orderBy('sh.actual_shipment_date')
            ->orderBy('sh.id')
            ->orderBy('sl.line_no')
            ->get([
                'sl.id as line_id', 'sl.line_no', 'sl.product_id', 'sl.confirmed_quantity as quantity',
                'sl.legacy_access_detail_id as detail_id', 'sh.id as header_id', 'sh.document_number',
                'sh.legacy_access_document_number as legacy_document_number', 'sh.actual_shipment_date as movement_date',
                'p.legacy_code as product_legacy_code',
            ]);
    }

    private function operationRows(string $start, string $end): Collection
    {
        return DB::table('non_sales_stock_operation_lines as l')
            ->join('non_sales_stock_operation_headers as h', 'h.id', '=', 'l.non_sales_stock_operation_header_id')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->where('h.status', 'confirmed')
            ->whereNull('h.cancelled_at')
            ->where('p.product_type', 'sake')
            ->where('l.quantity', '!=', 0)
            ->whereBetween('h.operation_date', [$start, $end])
            ->orderBy('h.operation_date')
            ->orderBy('h.id')
            ->orderBy('l.line_no')
            ->get([
                'l.id as line_id', 'l.line_no', 'l.product_id', 'l.quantity', 'l.production_lot_id',
                'l.stock_movement_id', 'l.legacy_access_detail_id as detail_id', 'h.operation_number',
                'h.operation_type', 'h.operation_date as movement_date', 'h.reason',
                'h.legacy_access_stock_operation_id as legacy_operation_id', 'p.legacy_code as product_legacy_code',
            ]);
    }

    /** @return array<string, ProductionLot> */
    private function resolveLots(Collection $shipments, Collection $operations): array
    {
        $lots = [];
        foreach ($shipments->concat($operations) as $row) {
            $key = $this->lotKey($row);
            if (isset($lots[$key])) {
                continue;
            }
            $canonical = 'ITARO-PRODUCT-DETAIL-'.$row->product_legacy_code.'-'.$row->detail_id;
            $lot = ProductionLot::query()->where('external_system_code', $canonical)->first();
            if ($lot === null && isset($row->production_lot_id) && $row->production_lot_id !== null) {
                $lot = ProductionLot::query()->find($row->production_lot_id);
            }
            $lot ??= ProductionLot::query()->where('external_system_code', 'ITARO-DETAIL-'.$row->detail_id)->first();
            if ($lot === null || $lot->stock_location_id === null || $lot->unit_id === null) {
                throw new RuntimeException("商品{$row->product_legacy_code}・詳細{$row->detail_id}の在庫ロットを解決できません。");
            }
            $lots[$key] = $lot;
        }

        return $lots;
    }

    private function lotKey(object $row): string
    {
        return $row->product_legacy_code.'|'.$row->detail_id;
    }

    /** @return array<string, mixed> */
    private function expectedStock(AccessMigrationBatch $batch, string $end): array
    {
        $map = $this->expectedStockMap($batch, $end);

        return ['count' => count($map), 'total' => $this->sumArray($map)];
    }

    /** @return array<string, string> */
    private function expectedStockMap(AccessMigrationBatch $batch, string $end): array
    {
        $expected = [];
        foreach ($this->calculator->calculateRows($batch, $end) as $row) {
            if ($row->product_type !== 'sake') {
                continue;
            }
            $quantity = bcadd((string) $row->calculated_stock, '0', 4);
            if (bccomp($quantity, '0.0000', 4) !== 0) {
                $expected[$row->access_product_id.'|'.$row->access_detail_id] = $quantity;
            }
        }

        return $expected;
    }

    private function sum(Collection $rows, callable $value): string
    {
        return $rows->reduce(fn (string $carry, object $row): string => bcadd($carry, $value($row), 4), '0.0000');
    }

    /** @param array<string, string> $values */
    private function sumArray(array $values): string
    {
        return array_reduce($values, fn (string $carry, string $value): string => bcadd($carry, $value, 4), '0.0000');
    }
}
