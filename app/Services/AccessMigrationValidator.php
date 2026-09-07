<?php

namespace App\Services;

use App\Models\AccessMigrationBatch;
use App\Models\AccessMigrationIssue;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AccessMigrationValidator
{
    public function validate(AccessMigrationBatch $batch): AccessMigrationBatch
    {
        if (! in_array($batch->status, ['staged', 'ready', 'ready_with_warnings', 'blocked'], true)) {
            throw new RuntimeException("検証できないバッチ状態です: {$batch->status}");
        }

        return DB::transaction(function () use ($batch): AccessMigrationBatch {
            $batch->issues()->delete();
            $checks = [];

            $checks[] = $this->recordCountCheck($batch);
            $checks[] = $this->referenceCheck(
                $batch,
                'shipment_line_without_header',
                '出荷伝票・商品',
                '出荷伝票・取引先',
                '伝票番号',
                '出荷明細に対応する出荷ヘッダーがありません。',
            );
            $checks[] = $this->referenceCheck(
                $batch,
                'shipment_without_customer',
                '出荷伝票・取引先',
                '取引先マスター',
                '取引先ID',
                '出荷ヘッダーに対応する得意先がありません。',
            );
            $checks[] = $this->referenceCheck(
                $batch,
                'shipment_line_without_product',
                '出荷伝票・商品',
                '商品マスター',
                '商品ID',
                '出荷明細に対応する商品がありません。',
            );
            $checks[] = $this->quantityWarning($batch, 'zero_quantity', '= 0', '個数0の出荷明細があります。');
            $checks[] = $this->quantityWarning($batch, 'negative_quantity', '< 0', '個数が負の出荷明細があります。');
            $checks[] = $this->futureShipmentWarning($batch);
            $checks[] = $this->headerAmountWarning($batch);
            $checks[] = $this->liquorTaxFlagWarning($batch);

            $errorCount = collect($checks)->where('severity', 'error')->sum('count');
            $warningCount = collect($checks)->where('severity', 'warning')->sum('count');
            $status = $errorCount > 0 ? 'blocked' : ($warningCount > 0 ? 'ready_with_warnings' : 'ready');

            $batch->update([
                'status' => $status,
                'error_count' => $errorCount,
                'warning_count' => $warningCount,
                'validation_summary' => ['checks' => $checks],
                'completed_at' => now(),
            ]);

            return $batch->refresh();
        });
    }

    private function recordCountCheck(AccessMigrationBatch $batch): array
    {
        $mismatches = $batch->tables()
            ->whereColumn('source_row_count', '<>', 'staged_row_count')
            ->count();

        return $this->issue(
            $batch,
            'error',
            'staged_row_count_mismatch',
            $mismatches,
            'manifestとステージングの行数が一致しないテーブルがあります。',
        );
    }

    private function referenceCheck(
        AccessMigrationBatch $batch,
        string $code,
        string $childTable,
        string $parentTable,
        string $foreignField,
        string $message,
    ): array {
        $foreignValue = "c.payload->>'{$foreignField}'";
        $count = $this->rows($batch, $childTable, 'c')
            ->leftJoin('access_migration_staging_rows as p', function ($join) use ($batch, $parentTable, $foreignValue): void {
                $join->on('p.source_key', '=', DB::raw($foreignValue))
                    ->where('p.batch_id', $batch->id)
                    ->where('p.source_table', $parentTable);
            })
            ->whereNull('p.id')
            ->count();

        return $this->issue($batch, 'error', $code, $count, $message, $childTable);
    }

    private function quantityWarning(
        AccessMigrationBatch $batch,
        string $code,
        string $comparison,
        string $message,
    ): array {
        $rows = $this->rows($batch, '出荷伝票・商品')
            ->select(['id', 'source_row_number', 'source_key'])
            ->whereRaw("CAST(payload->>'個数' AS numeric) {$comparison}")
            ->orderBy('id');

        return $this->rowIssues($batch, 'warning', $code, $message, '出荷伝票・商品', $rows);
    }

    private function futureShipmentWarning(AccessMigrationBatch $batch): array
    {
        $rows = $this->rows($batch, '出荷伝票・取引先')
            ->select(['id', 'source_row_number', 'source_key'])
            ->whereRaw("CAST(payload->>'年月日' AS date) > CURRENT_DATE")
            ->orderBy('id');

        return $this->rowIssues(
            $batch,
            'warning',
            'future_shipment_date',
            '抽出日より未来日の出荷伝票があります。',
            '出荷伝票・取引先',
            $rows,
        );
    }

    private function headerAmountWarning(AccessMigrationBatch $batch): array
    {
        $details = $this->rows($batch, '出荷伝票・商品')
            ->selectRaw("payload->>'伝票番号' as document_number")
            ->selectRaw("SUM(CAST(payload->>'取引額' AS numeric)) as detail_amount")
            ->groupByRaw("payload->>'伝票番号'");

        $rows = $this->rows($batch, '出荷伝票・取引先', 'h')
            ->select(['h.id', 'h.source_row_number', 'h.source_key'])
            ->joinSub($details, 'd', fn ($join) => $join->on('d.document_number', '=', 'h.source_key'))
            ->whereRaw("ABS(CAST(h.payload->>'金額' AS numeric) - d.detail_amount) > 0.0001")
            ->orderBy('h.id');

        return $this->rowIssues(
            $batch,
            'warning',
            'shipment_amount_mismatch',
            '出荷ヘッダー金額と明細取引額合計が一致しない伝票があります。',
            '出荷伝票・取引先',
            $rows,
        );
    }

    private function liquorTaxFlagWarning(AccessMigrationBatch $batch): array
    {
        $rows = $this->rows($batch, '出荷伝票・取引先', 'h')
            ->select(['h.id', 'h.source_row_number', 'h.source_key'])
            ->join('access_migration_staging_rows as c', function ($join) use ($batch): void {
                $join->on('c.source_key', '=', DB::raw("h.payload->>'酒税区分'"))
                    ->where('c.batch_id', $batch->id)
                    ->where('c.source_table', '酒税区分');
            })
            ->where(function (Builder $query): void {
                $query->whereRaw("COALESCE(h.payload->>'戻入取引', 'false') <> COALESCE(c.payload->>'戻入取引', 'false')")
                    ->orWhereRaw("COALESCE(h.payload->>'酒税未納取引', 'false') <> COALESCE(c.payload->>'酒税未納取引', 'false')")
                    ->orWhereRaw("COALESCE(h.payload->>'輸出取引', 'false') <> COALESCE(c.payload->>'輸出取引', 'false')");
            })
            ->orderBy('h.id');

        return $this->rowIssues(
            $batch,
            'warning',
            'liquor_tax_classification_flag_mismatch',
            '酒税区分と旧来の個別フラグが一致しない伝票があります。酒税区分を優先し、原値を保持します。',
            '出荷伝票・取引先',
            $rows,
        );
    }

    private function rows(AccessMigrationBatch $batch, string $sourceTable, ?string $alias = null): Builder
    {
        $alias ??= 'access_migration_staging_rows';

        return DB::table("access_migration_staging_rows as {$alias}")
            ->where("{$alias}.batch_id", $batch->id)
            ->where("{$alias}.source_table", $sourceTable);
    }

    private function issue(
        AccessMigrationBatch $batch,
        string $severity,
        string $code,
        int $count,
        string $message,
        ?string $sourceTable = null,
    ): array {
        if ($count > 0) {
            AccessMigrationIssue::query()->create([
                'batch_id' => $batch->id,
                'severity' => $severity,
                'issue_code' => $code,
                'source_table' => $sourceTable,
                'message' => $message,
                'context' => ['count' => $count],
            ]);
        }

        return compact('code', 'severity', 'count', 'message');
    }

    private function rowIssues(
        AccessMigrationBatch $batch,
        string $severity,
        string $code,
        string $message,
        string $sourceTable,
        Builder $rows,
    ): array {
        $count = (clone $rows)->count();
        if ($count === 0) {
            return compact('code', 'severity', 'count', 'message');
        }

        $now = now();
        $records = [];
        foreach ($rows->cursor() as $row) {
            $records[] = [
                'batch_id' => $batch->id,
                'severity' => $severity,
                'issue_code' => $code,
                'source_table' => $sourceTable,
                'source_row_number' => $row->source_row_number,
                'source_key' => $row->source_key,
                'message' => $message,
                'context' => json_encode(['batch_id' => $batch->id], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($records) === 500) {
                DB::table('access_migration_issues')->insert($records);
                $records = [];
            }
        }

        if ($records !== []) {
            DB::table('access_migration_issues')->insert($records);
        }

        return compact('code', 'severity', 'count', 'message');
    }
}
