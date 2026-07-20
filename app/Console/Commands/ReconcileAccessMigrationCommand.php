<?php

namespace App\Console\Commands;

use App\Models\AccessMigrationBatch;
use App\Services\ReconcileAccessMigration;
use Illuminate\Console\Command;
use Throwable;

class ReconcileAccessMigrationCommand extends Command
{
    protected $signature = 'aikura:access-reconcile {batch : Access移行バッチID} {--finalize : 全照合一致時にバッチを確定する}';

    protected $description = 'Generate an Access migration reconciliation workbook and optionally finalize the batch.';

    public function handle(ReconcileAccessMigration $service): int
    {
        $batch = AccessMigrationBatch::query()->find($this->argument('batch'));
        if ($batch === null) {
            $this->error('Access移行バッチが見つかりません。');

            return self::FAILURE;
        }

        try {
            $result = $service->run($batch, (bool) $this->option('finalize'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['判定', '検査数', '不一致', '出力先', 'バッチ状態'], [[
            $result['passed'] ? '一致' : '不一致',
            $result['check_count'],
            $result['mismatch_count'],
            storage_path('app/'.$result['report_path']),
            $batch->refresh()->status,
        ]]);

        return $result['passed'] ? self::SUCCESS : self::FAILURE;
    }
}
