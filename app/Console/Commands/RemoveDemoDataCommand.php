<?php

namespace App\Console\Commands;

use App\Services\RemoveDemoData;
use Illuminate\Console\Command;

class RemoveDemoDataCommand extends Command
{
    protected $signature = 'aikura:remove-demo-data {--force : デモデータを実際に削除する}';

    protected $description = 'Remove explicitly marked demo data and its dependent transactions.';

    public function handle(RemoveDemoData $service): int
    {
        $summary = $this->option('force') ? $service->remove() : $service->summary();
        $this->info($this->option('force') ? 'デモデータを削除しました。' : '削除対象の確認結果です。--forceで削除します。');
        $this->table(['対象', '件数'], collect($summary)->map(fn ($count, $key) => [$key, $count])->values()->all());

        return self::SUCCESS;
    }
}
