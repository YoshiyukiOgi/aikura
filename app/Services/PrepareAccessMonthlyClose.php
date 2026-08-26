<?php

namespace App\Services;

use App\Models\AccessMigrationBatch;
use App\Models\ReceivableMonthlyBalance;
use App\Models\StockLotMonthlyBalance;
use App\Services\Billing\ImportLegacyReceivableClosings;
use App\Services\Inventory\CreateInventoryCountDraftService;
use App\Services\Inventory\CreateStockMonthlyBalanceDraftService;
use App\Services\Inventory\PrepareAccessStockMovementsService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PrepareAccessMonthlyClose
{
    public function __construct(
        private readonly ImportLegacyReceivableClosings $receivables,
        private readonly PrepareAccessStockMovementsService $stockMovements,
        private readonly CreateInventoryCountDraftService $inventoryCounts,
        private readonly CreateStockMonthlyBalanceDraftService $stockBalances,
    ) {}

    /** @return array<string, mixed> */
    public function preview(AccessMigrationBatch $batch, int $year, int $month, ?string $expectedReceivableTotal = null): array
    {
        $this->guard($batch, $year, $month);
        $receivables = $this->receivables->projectMonthFromAccess($batch->id, $year, $month, false, 'draft');
        $this->assertReceivableTotal($receivables['total'], $expectedReceivableTotal);

        return [
            'applied' => false,
            'batch_id' => $batch->id,
            'year' => $year,
            'month' => $month,
            'receivables' => $receivables,
            'stock' => $this->stockMovements->preview($batch, $year, $month),
        ];
    }

    /** @return array<string, mixed> */
    public function apply(AccessMigrationBatch $batch, int $year, int $month, ?string $expectedReceivableTotal = null): array
    {
        $plan = $this->preview($batch, $year, $month, $expectedReceivableTotal);

        return DB::transaction(function () use ($batch, $year, $month, $expectedReceivableTotal, $plan): array {
            $stock = $this->stockMovements->apply($batch, $year, $month);
            $inventoryCount = $this->inventoryCounts->create($year, $month, 'Access月次在庫締め準備（実棚数量は未入力）');
            $stockDrafts = $this->stockBalances->create($year, $month, 'Access月次在庫締め準備');
            $stockDraftTotal = $stockDrafts->reduce(
                fn (string $carry, StockLotMonthlyBalance $balance): string => bcadd($carry, (string) $balance->closing_quantity, 4),
                '0.0000',
            );
            if (bccomp($stockDraftTotal, $stock['reconciliation']['expected_total'], 4) !== 0) {
                throw new RuntimeException("在庫月次ドラフト合計がAccess計算値と一致しません: {$stockDraftTotal}");
            }

            $receivables = $this->receivables->projectMonthFromAccess($batch->id, $year, $month, true, 'draft');
            $this->assertReceivableTotal($receivables['total'], $expectedReceivableTotal);
            $receivableDraftTotal = ReceivableMonthlyBalance::query()
                ->where('year', $year)
                ->where('month', $month)
                ->where('status', 'draft')
                ->sum('outstanding_amount');
            if (bccomp((string) $receivableDraftTotal, $receivables['total'], 2) !== 0) {
                throw new RuntimeException("請求月次ドラフト合計がAccess計算値と一致しません: {$receivableDraftTotal}");
            }

            return array_replace($plan, [
                'applied' => true,
                'stock' => $stock + [
                    'draft_count' => $stockDrafts->count(),
                    'draft_total' => $stockDraftTotal,
                    'inventory_count_id' => $inventoryCount->id,
                    'inventory_count_status' => $inventoryCount->status,
                    'inventory_count_line_count' => $inventoryCount->lines->count(),
                    'inventory_count_uncounted_count' => $inventoryCount->lines->whereNull('counted_quantity')->count(),
                ],
                'receivables' => $receivables + [
                    'draft_count' => ReceivableMonthlyBalance::query()->where('year', $year)->where('month', $month)->where('status', 'draft')->count(),
                    'draft_total' => bcadd((string) $receivableDraftTotal, '0', 2),
                ],
            ]);
        }, 3);
    }

    private function guard(AccessMigrationBatch $batch, int $year, int $month): void
    {
        if ($batch->status !== 'completed') {
            throw new RuntimeException("完了済みAccessバッチだけを締め準備に使用できます: {$batch->status}");
        }
        if (ReceivableMonthlyBalance::query()->where('year', $year)->where('month', $month)->whereIn('status', ['confirmed', 'closed'])->exists()) {
            throw new RuntimeException('対象月の請求残高はすでに確定または締め済みです。');
        }
        if (StockLotMonthlyBalance::query()->where('year', $year)->where('month', $month)->whereIn('status', ['confirmed', 'closed'])->exists()) {
            throw new RuntimeException('対象月の在庫残高はすでに確定または締め済みです。');
        }
    }

    private function assertReceivableTotal(string $actual, ?string $expected): void
    {
        if ($expected !== null && bccomp($actual, $expected, 2) !== 0) {
            throw new RuntimeException("7月請求書PDF合計と一致しません: expected={$expected}, actual={$actual}");
        }
    }
}
