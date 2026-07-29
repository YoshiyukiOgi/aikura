<?php

namespace App\Console\Commands;

use App\Models\AccessMigrationBatch;
use App\Services\ApplyAccessMigrationDelta;
use Illuminate\Console\Command;
use Throwable;

class ApplyAccessMigrationDeltaCommand extends Command
{
    protected $signature = 'aikura:access-delta-apply
        {batch : 差分計画を確認済みのAccess移行バッチID}
        {--yes : 確認プロンプトを省略する}';

    protected $description = 'Apply a reviewed Access delta plan without recalculating opening balances.';

    public function handle(ApplyAccessMigrationDelta $applier): int
    {
        $batch = AccessMigrationBatch::query()->find($this->argument('batch'));
        if ($batch === null) {
            $this->error('Access移行バッチが見つかりません。');

            return self::FAILURE;
        }

        $summary = $batch->delta_summary ?? [];
        $this->table(['新規', '変更', '不変', '削除', '適用停止要因'], [[
            $summary['new'] ?? '-',
            $summary['changed'] ?? '-',
            $summary['unchanged'] ?? '-',
            $summary['deleted'] ?? '-',
            $summary['blockers'] ?? '-',
        ]]);
        if (! $this->option('yes') && ! $this->confirm('この差分計画を適用しますか？')) {
            $this->warn('適用を中止しました。');

            return self::SUCCESS;
        }

        try {
            $result = $applier->apply($batch);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Access差分の適用が完了しました。開始在庫・開始売掛残高は再計算していません。');
        $this->table(['対象', '件数'], [
            ['出荷伝票', $result['shipments']['shipment_headers'] ?? 0],
            ['出荷明細', $result['shipments']['shipment_lines'] ?? 0],
            ['在庫外操作', $result['inventory_history']['operation_headers'] ?? 0],
            ['入金台帳', $result['receivables']['ledger_entries'] ?? 0],
            ['入金', $result['receivables']['payments'] ?? 0],
        ]);

        return self::SUCCESS;
    }
}
