<?php

namespace App\Console\Commands;

use App\Models\AccessMigrationBatch;
use App\Services\ImportAccessPrices;
use Illuminate\Console\Command;
use Throwable;

class ImportAccessPricesCommand extends Command
{
    protected $signature = 'aikura:access-import-prices {batch : マスタ移行済みAccess移行バッチID}';

    protected $description = 'Import Access default and customer-specific prices into price rules.';

    public function handle(ImportAccessPrices $importer): int
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

        $this->info('Access価格移行が完了しました。');
        $this->table(['対象', '件数'], [
            ['既定価格ルール', $summary['default_price_rules']],
            ['取引先別価格ルール', $summary['customer_price_rules']],
            ['スキップした既定価格行', $summary['skipped_default_price_rows']],
            ['スキップした取引先別価格行', $summary['skipped_customer_price_rows']],
        ]);

        return self::SUCCESS;
    }
}
