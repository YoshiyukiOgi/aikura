<?php

namespace App\Services;

use App\Models\AccessMigrationBatch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ApplyAccessMigrationDelta
{
    private const TRANSACTION_TABLES = [
        '出荷伝票・取引先',
        '出荷伝票・商品',
        '伝票外在庫出入',
        '入金',
    ];

    public function __construct(
        private readonly ImportAccessMasters $masters,
        private readonly ImportAccessPrices $prices,
        private readonly ImportAccessShipments $shipments,
        private readonly ImportAccessInventoryHistory $inventoryHistory,
        private readonly ImportAccessReceivables $receivables,
    ) {}

    public function apply(AccessMigrationBatch $batch): array
    {
        if ($batch->status === 'delta_applied') {
            return $batch->delta_summary['apply'] ?? [];
        }
        if ($batch->baseline_batch_id === null || $batch->delta_planned_at === null) {
            throw new RuntimeException('先に aikura:access-delta-plan を実行してください。');
        }
        if (! in_array($batch->status, [
            'ready',
            'ready_with_warnings',
            'masters_imported',
            'prices_imported',
            'shipments_imported',
            'inventory_history_imported',
            'receivables_imported',
        ], true)) {
            throw new RuntimeException("差分を適用できないバッチ状態です: {$batch->status}");
        }

        $blockers = DB::table('access_migration_deltas')
            ->where('batch_id', $batch->id)
            ->where(function ($query): void {
                $query->where('change_type', 'deleted')
                    ->orWhere(function ($changed): void {
                        $changed->where('change_type', 'changed')
                            ->whereIn('source_table', self::TRANSACTION_TABLES);
                    });
            })
            ->get(['source_table', 'source_key', 'change_type']);
        if ($blockers->isNotEmpty()) {
            $examples = $blockers->take(5)
                ->map(fn (object $row): string => "{$row->source_table}/{$row->source_key}({$row->change_type})")
                ->implode(', ');
            throw new RuntimeException("自動適用できない変更があります。差分CSVを確認してください: {$examples}");
        }

        $monthlyBlockers = $this->monthlyClosingBlockers($batch);
        if ($monthlyBlockers !== []) {
            throw new RuntimeException('月次確定・締め済み期間へ影響する差分があります。月次解除または訂正手順を承認してください: '.implode(', ', array_slice($monthlyBlockers, 0, 5)));
        }

        $summaries = $batch->validation_summary['imports'] ?? [];
        $status = $batch->status;
        if (in_array($status, ['ready', 'ready_with_warnings'], true)) {
            $summaries['masters'] = $this->masters->import($batch->fresh());
            $status = 'masters_imported';
        }
        if ($status === 'masters_imported') {
            $summaries['prices'] = $this->prices->import($batch->fresh());
            $batch->update([
                'status' => 'prices_imported',
                'completed_at' => now(),
            ]);
            $status = 'prices_imported';
        }
        if ($status === 'prices_imported') {
            $summaries['shipments'] = $this->hasNewRows($batch, ['出荷伝票・取引先', '出荷伝票・商品'])
                ? $this->shipments->import($batch->fresh(), true)
                : $this->emptyShipmentSummary();
            if ($batch->fresh()->status !== 'shipments_imported') {
                $this->checkpoint($batch, 'shipments_imported');
            }
            $status = 'shipments_imported';
        }
        if ($status === 'shipments_imported') {
            $summaries['inventory_history'] = $this->hasNewRows($batch, ['伝票外在庫出入'])
                ? $this->inventoryHistory->import($batch->fresh(), true)
                : $this->emptyInventoryHistorySummary();
            if ($batch->fresh()->status !== 'inventory_history_imported') {
                $this->checkpoint($batch, 'inventory_history_imported');
            }
            $status = 'inventory_history_imported';
        }
        if ($status === 'inventory_history_imported') {
            $summaries['receivables'] = $this->hasNewRows($batch, ['入金'])
                ? $this->receivables->import($batch->fresh(), true)
                : $this->emptyReceivableSummary();
            if ($batch->fresh()->status !== 'receivables_imported') {
                $this->checkpoint($batch, 'receivables_imported');
            }
        }

        DB::transaction(function () use ($batch, $summaries): void {
            DB::table('access_migration_deltas')
                ->where('batch_id', $batch->id)
                ->whereIn('change_type', ['new', 'changed'])
                ->update(['apply_status' => 'applied', 'updated_at' => now()]);
            DB::table('access_migration_deltas')
                ->where('batch_id', $batch->id)
                ->where('change_type', 'unchanged')
                ->update(['apply_status' => 'skipped', 'note' => '前回と同一', 'updated_at' => now()]);

            $deltaSummary = $batch->fresh()->delta_summary ?? [];
            $deltaSummary['apply'] = $summaries;
            $batch->update([
                'status' => 'delta_applied',
                'delta_summary' => $deltaSummary,
                'delta_applied_at' => now(),
                'completed_at' => now(),
            ]);
        });

        return $summaries;
    }

    /**
     * Aの月次確定値を維持するため、締め済み月への新規B1取引は自動適用しない。
     * 変更・削除は呼出元で先に全面停止している。
     *
     * @return list<string>
     */
    private function monthlyClosingBlockers(AccessMigrationBatch $batch): array
    {
        $rows = DB::table('access_migration_deltas as delta')
            ->join('access_migration_staging_rows as staging', 'staging.id', '=', 'delta.current_staging_row_id')
            ->where('delta.batch_id', $batch->id)
            ->where('delta.change_type', 'new')
            ->whereIn('delta.source_table', self::TRANSACTION_TABLES)
            ->get(['delta.source_table', 'delta.source_key', 'staging.payload']);

        $headerDates = [];
        $periodLocks = [];
        $blockers = [];

        foreach ($rows as $row) {
            $payload = json_decode($row->payload, true);
            $date = is_array($payload) ? $this->sourceDate($batch, $row->source_table, $payload, $headerDates) : null;
            if ($date === null) {
                $blockers[] = "{$row->source_table}/{$row->source_key}(取引日不明)";

                continue;
            }

            $periodKey = $date->format('Y-m');
            $periodLocks[$periodKey] ??= $this->isMonthlyPeriodLocked((int) $date->year, (int) $date->month);
            if ($periodLocks[$periodKey]) {
                $blockers[] = "{$row->source_table}/{$row->source_key}({$periodKey})";
            }
        }

        return $blockers;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, CarbonImmutable|null> $headerDates
     */
    private function sourceDate(AccessMigrationBatch $batch, string $sourceTable, array $payload, array &$headerDates): ?CarbonImmutable
    {
        if ($sourceTable === '出荷伝票・商品') {
            $documentNumber = trim((string) ($payload['伝票番号'] ?? ''));
            if ($documentNumber === '') {
                return null;
            }
            if (! array_key_exists($documentNumber, $headerDates)) {
                $headerPayload = DB::table('access_migration_staging_rows')
                    ->where('batch_id', $batch->id)
                    ->where('source_table', '出荷伝票・取引先')
                    ->where('source_key', $documentNumber)
                    ->value('payload');
                $header = is_string($headerPayload) ? json_decode($headerPayload, true) : null;
                $headerDates[$documentNumber] = is_array($header)
                    ? $this->parseSourceDate($header['年月日'] ?? null)
                    : null;
            }

            return $headerDates[$documentNumber];
        }

        return $this->parseSourceDate($payload['年月日'] ?? null);
    }

    private function parseSourceDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function isMonthlyPeriodLocked(int $year, int $month): bool
    {
        return DB::table('stock_lot_monthly_balances')
            ->where('year', $year)
            ->where('month', $month)
            ->whereIn('status', ['confirmed', 'closed'])
            ->exists()
            || DB::table('receivable_monthly_balances')
                ->where('year', $year)
                ->where('month', $month)
                ->whereIn('status', ['confirmed', 'closed'])
                ->exists()
            || DB::table('liquor_tax_monthly_filings')
                ->where('year', $year)
                ->where('month', $month)
                ->whereIn('status', ['confirmed', 'closed'])
                ->exists()
            || DB::table('consumption_tax_monthly_filings')
                ->where('year', $year)
                ->where('month', $month)
                ->whereIn('status', ['confirmed', 'closed'])
                ->exists();
    }

    /** @param list<string> $sourceTables */
    private function hasNewRows(AccessMigrationBatch $batch, array $sourceTables): bool
    {
        return DB::table('access_migration_deltas')
            ->where('batch_id', $batch->id)
            ->where('change_type', 'new')
            ->whereIn('source_table', $sourceTables)
            ->exists();
    }

    private function checkpoint(AccessMigrationBatch $batch, string $status): void
    {
        $batch->update(['status' => $status, 'completed_at' => now()]);
    }

    /** @return array<string, int|string|null> */
    private function emptyShipmentSummary(): array
    {
        return [
            'shipment_headers' => 0,
            'shipment_lines' => 0,
            'tax_review_headers' => 0,
            'stock_movements_created' => 0,
            'cutover_date' => null,
            'imported_at' => now()->toIso8601String(),
        ];
    }

    /** @return array<string, int|string|null> */
    private function emptyInventoryHistorySummary(): array
    {
        return [
            'production_lots' => 0,
            'operation_headers' => 0,
            'operation_lines' => 0,
            'tax_review_headers' => 0,
            'stock_movements_created' => 0,
            'cutover_date' => null,
            'returns_already_imported_as_shipments' => 0,
            'imported_at' => now()->toIso8601String(),
        ];
    }

    /** @return array<string, int|string|null> */
    private function emptyReceivableSummary(): array
    {
        return [
            'ledger_entries' => 0,
            'payments' => 0,
            'opening_balances' => 0,
            'non_zero_opening_balances' => 0,
            'opening_balance_total' => '0.00',
            'as_of_date' => null,
            'cutover_date' => null,
            'imported_at' => now()->toIso8601String(),
        ];
    }
}
