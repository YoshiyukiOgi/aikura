<?php

namespace App\Services;

use App\Models\AccessMigrationBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class PlanAccessMigrationDelta
{
    private const TRANSACTION_TABLES = [
        '出荷伝票・取引先',
        '出荷伝票・商品',
        '伝票外在庫出入',
        '入金',
    ];

    public function plan(AccessMigrationBatch $batch, ?AccessMigrationBatch $baseline = null): array
    {
        if (! in_array($batch->status, ['ready', 'ready_with_warnings'], true)) {
            throw new RuntimeException("差分計画を作成できないバッチ状態です: {$batch->status}");
        }

        $baseline ??= AccessMigrationBatch::query()
            ->where('id', '<>', $batch->id)
            ->where('id', '<', $batch->id)
            ->whereIn('status', [
                'masters_imported',
                'shipments_imported',
                'inventory_history_imported',
                'opening_stock_imported',
                'receivables_imported',
                'completed',
                'delta_applied',
            ])
            ->latest('id')
            ->first();

        if ($baseline === null) {
            throw new RuntimeException('比較元バッチが見つかりません。--baseline で指定してください。');
        }
        if ($baseline->is($batch)) {
            throw new RuntimeException('対象バッチ自身を比較元には指定できません。');
        }

        DB::transaction(function () use ($batch, $baseline): void {
            DB::table('access_migration_deltas')->where('batch_id', $batch->id)->delete();

            DB::table('access_migration_staging_rows')
                ->where('batch_id', $batch->id)
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($batch, $baseline): void {
                    $baselineRows = DB::table('access_migration_staging_rows')
                        ->where('batch_id', $baseline->id)
                        ->where(function ($query) use ($rows): void {
                            foreach ($rows->groupBy('source_table') as $sourceTable => $tableRows) {
                                $query->orWhere(function ($tableQuery) use ($sourceTable, $tableRows): void {
                                    $tableQuery
                                        ->where('source_table', $sourceTable)
                                        ->whereIn('source_key', $tableRows->pluck('source_key'));
                                });
                            }
                        })
                        ->get()
                        ->keyBy(fn (object $row): string => $row->source_table."\0".$row->source_key);
                    $now = now();
                    $records = [];

                    foreach ($rows as $row) {
                        $previous = $baselineRows->get($row->source_table."\0".$row->source_key);
                        $currentPayloadHash = $this->canonicalPayloadHash($row->payload);
                        $baselinePayloadHash = $previous === null ? null : $this->canonicalPayloadHash($previous->payload);
                        $records[] = [
                            'batch_id' => $batch->id,
                            'baseline_batch_id' => $baseline->id,
                            'source_table' => $row->source_table,
                            'source_key' => $row->source_key,
                            'change_type' => $previous === null
                                ? 'new'
                                : ($baselinePayloadHash === $currentPayloadHash ? 'unchanged' : 'changed'),
                            'current_staging_row_id' => $row->id,
                            'baseline_staging_row_id' => $previous?->id,
                            'current_payload_sha256' => $currentPayloadHash,
                            'baseline_payload_sha256' => $baselinePayloadHash,
                            'apply_status' => 'planned',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    DB::table('access_migration_deltas')->insert($records);
                });

            DB::table('access_migration_staging_rows')
                ->where('batch_id', $baseline->id)
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($batch, $baseline): void {
                    $currentKeys = DB::table('access_migration_staging_rows')
                        ->where('batch_id', $batch->id)
                        ->where(function ($query) use ($rows): void {
                            foreach ($rows->groupBy('source_table') as $sourceTable => $tableRows) {
                                $query->orWhere(function ($tableQuery) use ($sourceTable, $tableRows): void {
                                    $tableQuery
                                        ->where('source_table', $sourceTable)
                                        ->whereIn('source_key', $tableRows->pluck('source_key'));
                                });
                            }
                        })
                        ->get(['source_table', 'source_key'])
                        ->mapWithKeys(fn (object $row): array => [$row->source_table."\0".$row->source_key => true]);
                    $now = now();
                    $records = [];

                    foreach ($rows as $row) {
                        if ($currentKeys->has($row->source_table."\0".$row->source_key)) {
                            continue;
                        }
                        $records[] = [
                            'batch_id' => $batch->id,
                            'baseline_batch_id' => $baseline->id,
                            'source_table' => $row->source_table,
                            'source_key' => $row->source_key,
                            'change_type' => 'deleted',
                            'current_staging_row_id' => null,
                            'baseline_staging_row_id' => $row->id,
                            'current_payload_sha256' => null,
                            'baseline_payload_sha256' => $this->canonicalPayloadHash($row->payload),
                            'apply_status' => 'planned',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if ($records !== []) {
                        DB::table('access_migration_deltas')->insert($records);
                    }
                });
        }, 3);

        $counts = DB::table('access_migration_deltas')
            ->where('batch_id', $batch->id)
            ->selectRaw('change_type, COUNT(*) AS aggregate')
            ->groupBy('change_type')
            ->pluck('aggregate', 'change_type');
        $blockers = DB::table('access_migration_deltas')
            ->where('batch_id', $batch->id)
            ->where(function ($query): void {
                $query->where('change_type', 'deleted');
            })
            ->count();
        $reportPath = $this->writeReport($batch);
        $summary = [
            'baseline_batch_id' => $baseline->id,
            'new' => (int) ($counts['new'] ?? 0),
            'changed' => (int) ($counts['changed'] ?? 0),
            'unchanged' => (int) ($counts['unchanged'] ?? 0),
            'deleted' => (int) ($counts['deleted'] ?? 0),
            'blockers' => $blockers,
            'report_path' => $reportPath,
        ];

        $batch->update([
            'baseline_batch_id' => $baseline->id,
            'delta_summary' => $summary,
            'delta_planned_at' => now(),
            'delta_applied_at' => null,
        ]);

        return $summary;
    }

    private function canonicalPayloadHash(mixed $payload): string
    {
        if (! is_string($payload)) {
            throw new RuntimeException('AccessステージングのJSONペイロードを正規化できません。');
        }

        // PostgreSQL JSONB returns object keys in a stable canonical order.
        return strtoupper(hash('sha256', $payload));
    }

    private function writeReport(AccessMigrationBatch $batch): string
    {
        $directory = storage_path('app/access-migrations/reports');
        File::ensureDirectoryExists($directory);
        $path = $directory."/access-delta-plan-batch-{$batch->id}.csv";
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException("差分CSVを作成できません: {$path}");
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['source_table', 'source_key', 'change_type', 'current_sha256', 'baseline_sha256']);
        DB::table('access_migration_deltas')
            ->where('batch_id', $batch->id)
            ->orderBy('source_table')
            ->orderBy('source_key')
            ->chunk(1000, function ($rows) use ($handle): void {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->source_table,
                        $row->source_key,
                        $row->change_type,
                        $row->current_payload_sha256,
                        $row->baseline_payload_sha256,
                    ]);
                }
            });
        fclose($handle);

        return $path;
    }
}
