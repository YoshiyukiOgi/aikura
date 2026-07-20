<?php

namespace App\Console\Commands;

use App\Models\AccessMigrationBatch;
use App\Services\ImportAccessOpeningStock;
use Illuminate\Console\Command;
use Throwable;

class ImportAccessOpeningStockCommand extends Command
{
    protected $signature = 'aikura:access-import-opening-stock
        {batch : Access移行バッチID}
        {--as-of=2026-06-30 : 在庫計算基準日}
        {--location=main_brewery : 登録先在庫場所コード}
        {--commit : 期首在庫をデータベースへ登録する}';

    protected $description = 'Preview or import Access detail-level calculated stock as opening stock.';

    public function handle(ImportAccessOpeningStock $service): int
    {
        $batch = AccessMigrationBatch::query()->find($this->argument('batch'));
        if ($batch === null) {
            $this->error('Access移行バッチが見つかりません。');

            return self::FAILURE;
        }

        try {
            $result = $this->option('commit')
                ? $service->import($batch, (string) $this->option('as-of'), (string) $this->option('location'))
                : $service->preview($batch, (string) $this->option('as-of'), (string) $this->option('location'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['実行', '基準日', '登録先', '非ゼロ', '正数', '負数', '数量合計', '不足ロット', '無効ロット'],
            [[
                $this->option('commit') ? '本登録' : 'ドライラン',
                $result['as_of_date'],
                $result['location_code'],
                $result['row_count'],
                $result['positive_count'],
                $result['negative_count'],
                $result['quantity_total'],
                $result['missing_lot_count'],
                $result['inactive_lot_count'],
            ]],
        );

        if ($this->option('commit')) {
            $this->line("ロット作成: {$result['lots_created']} / 有効化: {$result['lots_activated']}");
            $this->line("在庫移動作成: {$result['movements_created']} / 更新: {$result['movements_updated']}");
        } else {
            $this->warn('ドライランです。データベースは変更していません。本登録には --commit を指定します。');
        }

        return self::SUCCESS;
    }
}
