<?php

namespace App\Services;

use App\Models\AccessMigrationBatch;
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

        $summaries = $batch->validation_summary['imports'] ?? [];
        $status = $batch->status;
        if (in_array($status, ['ready', 'ready_with_warnings'], true)) {
            $summaries['masters'] = $this->masters->import($batch->fresh());
            $status = 'masters_imported';
        }
        if ($status === 'masters_imported') {
            $summaries['prices'] = $this->prices->import($batch->fresh());
            $summaries['shipments'] = $this->shipments->import($batch->fresh(), true);
            $status = 'shipments_imported';
        }
        if ($status === 'shipments_imported') {
            $summaries['inventory_history'] = $this->inventoryHistory->import($batch->fresh(), true);
            $status = 'inventory_history_imported';
        }
        if ($status === 'inventory_history_imported') {
            $summaries['receivables'] = $this->receivables->import($batch->fresh(), true);
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
}
