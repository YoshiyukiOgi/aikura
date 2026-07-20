<?php

namespace App\Console\Commands;

use App\Models\AccessMigrationBatch;
use App\Services\ExportAccessDetailStockWorkbook;
use Illuminate\Console\Command;
use Throwable;

class ExportAccessDetailStockCommand extends Command
{
    protected $signature = 'aikura:access-export-detail-stock {batch : Access移行バッチID} {--as-of=2026-06-30 : 在庫計算基準日}';

    protected $description = 'Export calculated Access stock grouped by product and detail ID to Excel.';

    public function handle(ExportAccessDetailStockWorkbook $service): int
    {
        $batch = AccessMigrationBatch::query()->find($this->argument('batch'));
        if ($batch === null) {
            $this->error('Access移行バッチが見つかりません。');

            return self::FAILURE;
        }

        try {
            $result = $service->export($batch, (string) $this->option('as-of'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['行数', '正在庫', '在庫0', '負在庫', '合計', '出力先'], [[
            $result['row_count'],
            $result['positive_count'],
            $result['zero_count'],
            $result['negative_count'],
            $result['calculated_stock_total'],
            storage_path('app/'.$result['path']),
        ]]);

        return self::SUCCESS;
    }
}
