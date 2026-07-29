<?php

namespace App\Console\Commands;

use App\Models\AccessMigrationBatch;
use App\Services\PlanAccessMigrationDelta;
use Illuminate\Console\Command;
use Throwable;

class PlanAccessMigrationDeltaCommand extends Command
{
    protected $signature = 'aikura:access-delta-plan
        {batch : 検証済みの新しいAccess移行バッチID}
        {--baseline= : 比較元バッチID。省略時は直前の移行済みバッチ}';

    protected $description = 'Compare two staged Access snapshots and create an auditable delta plan.';

    public function handle(PlanAccessMigrationDelta $planner): int
    {
        $batch = AccessMigrationBatch::query()->find($this->argument('batch'));
        $baseline = $this->option('baseline') === null
            ? null
            : AccessMigrationBatch::query()->find($this->option('baseline'));
        if ($batch === null || ($this->option('baseline') !== null && $baseline === null)) {
            $this->error('対象または比較元のAccess移行バッチが見つかりません。');

            return self::FAILURE;
        }

        try {
            $summary = $planner->plan($batch, $baseline);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Access差分計画を作成しました。');
        $this->table(['比較元', '新規', '変更', '不変', '削除', '適用停止要因'], [[
            $summary['baseline_batch_id'],
            $summary['new'],
            $summary['changed'],
            $summary['unchanged'],
            $summary['deleted'],
            $summary['blockers'],
        ]]);
        $this->line("CSV: {$summary['report_path']}");

        return self::SUCCESS;
    }
}
