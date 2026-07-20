<?php

namespace App\Console\Commands;

use App\Models\AccessMigrationBatch;
use App\Services\ImportAccessInventoryHistory;
use Illuminate\Console\Command;
use Throwable;

class ImportAccessInventoryHistoryCommand extends Command
{
    protected $signature = 'aikura:access-import-inventory-history {batch : 出荷履歴移行済みAccess移行バッチID}';

    protected $description = 'Import Access non-sales inventory history without affecting current stock.';

    public function handle(ImportAccessInventoryHistory $importer): int
    {
        $batch = AccessMigrationBatch::query()->find($this->argument('batch'));
        if ($batch === null) {
            $this->error('Access移行バッチが見つかりません。');

            return self::FAILURE;
        }

        try {
            $summary = $importer->import($batch);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Access伝票外在庫出入の履歴移行が完了しました。');
        $this->table(['対象', '件数'], [
            ['履歴ロット', $summary['production_lots']],
            ['在庫出入ヘッダー', $summary['operation_headers']],
            ['在庫出入明細', $summary['operation_lines']],
            ['要税務確認', $summary['tax_review_headers']],
            ['在庫移動作成', $summary['stock_movements_created']],
        ]);

        return self::SUCCESS;
    }
}
