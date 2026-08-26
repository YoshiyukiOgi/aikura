<?php

namespace App\Console\Commands;

use App\Models\AccessMigrationBatch;
use App\Services\PrepareAccessMonthlyClose;
use Illuminate\Console\Command;
use Throwable;

class PrepareAccessMonthlyCloseCommand extends Command
{
    protected $signature = 'aikura:access-prepare-monthly-close
        {batch : 稼働データに使用した完了済みAccess移行バッチID}
        {year : 対象年}
        {month : 対象月}
        {--expected-receivable-total= : 紙請求書の月末残高合計}
        {--apply : 在庫移動と月次ドラフトを登録する}';

    protected $description = 'Access由来の出荷・入金・在庫出入を照合し、請求と在庫を月次締め直前のドラフト状態へ準備する。';

    public function handle(PrepareAccessMonthlyClose $service): int
    {
        $batch = AccessMigrationBatch::query()->find($this->argument('batch'));
        if ($batch === null) {
            $this->error('Access移行バッチが見つかりません。');

            return self::FAILURE;
        }

        try {
            $result = $this->option('apply')
                ? $service->apply($batch, (int) $this->argument('year'), (int) $this->argument('month'), $this->option('expected-receivable-total') ?: null)
                : $service->preview($batch, (int) $this->argument('year'), (int) $this->argument('month'), $this->option('expected-receivable-total') ?: null);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($result['applied'] ? '月次締め直前のドラフト状態へ準備しました。締めは実行していません。' : 'ドライランです。データは変更していません。');
        $this->table(['項目', '値'], [
            ['Accessバッチ', $result['batch_id']],
            ['対象月', sprintf('%04d-%02d', $result['year'], $result['month'])],
            ['出荷伝票', $result['receivables']['shipment_count']],
            ['出荷合計', $result['receivables']['shipment_total']],
            ['入金・手数料', $result['receivables']['receipts_and_fees_total']],
            ['請求残高', $result['receivables']['total']],
            ['請求ドラフト', $result['receivables']['draft_count'] ?? '-'],
            ['出荷在庫移動', $result['stock']['shipment_movement_count'] ?? ($result['stock']['shipment_movements_created'] + $result['stock']['shipment_movements_updated'])],
            ['販売外在庫移動', $result['stock']['non_sales_movement_count'] ?? ($result['stock']['non_sales_movements_created'] + $result['stock']['non_sales_movements_updated'])],
            ['月末在庫合計', $result['stock']['reconciliation']['actual_total'] ?? $result['stock']['expected_stock']['total']],
            ['在庫差異', $result['stock']['reconciliation']['difference_count'] ?? '-'],
            ['在庫ドラフト', $result['stock']['draft_count'] ?? '-'],
            ['棚卸下書き', $result['stock']['inventory_count_status'] ?? '-'],
            ['棚卸明細', $result['stock']['inventory_count_line_count'] ?? '-'],
            ['実棚未入力', $result['stock']['inventory_count_uncounted_count'] ?? '-'],
        ]);

        return self::SUCCESS;
    }
}
