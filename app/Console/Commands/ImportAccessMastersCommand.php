<?php

namespace App\Console\Commands;

use App\Models\AccessMigrationBatch;
use App\Services\ImportAccessMasters;
use Illuminate\Console\Command;
use Throwable;

class ImportAccessMastersCommand extends Command
{
    protected $signature = 'aikura:access-import-masters {batch : 検証済みAccess移行バッチID}';

    protected $description = 'Import Access customers and products into operational master tables.';

    public function handle(ImportAccessMasters $importer): int
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

        $this->info('Accessマスタ移行が完了しました。');
        $this->table(['対象', '件数'], [
            ['得意先', $summary['customers']],
            ['得意先名フォールバック', $summary['customer_name_fallbacks']],
            ['商品', $summary['products']],
            ['移行元対応', $summary['mappings']],
        ]);

        return self::SUCCESS;
    }
}
