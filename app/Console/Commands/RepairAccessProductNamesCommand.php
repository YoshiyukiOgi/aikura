<?php

namespace App\Console\Commands;

use App\Models\AccessMigrationBatch;
use App\Services\RepairAccessProductNames;
use Illuminate\Console\Command;
use Throwable;

class RepairAccessProductNamesCommand extends Command
{
    protected $signature = 'aikura:access-repair-product-names {batch : Access移行バッチID}';

    protected $description = 'Repair composed Access product names in products, migrated shipments, and historical lots.';

    public function handle(RepairAccessProductNames $repairer): int
    {
        $batch = AccessMigrationBatch::query()->find($this->argument('batch'));
        if ($batch === null) {
            $this->error('Access移行バッチが見つかりません。');

            return self::FAILURE;
        }

        try {
            $summary = $repairer->repair($batch);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Access商品名の修復が完了しました。');
        $this->table(['対象', '件数'], [
            ['確認商品', $summary['products_examined']],
            ['変更商品', $summary['products_changed']],
            ['出荷明細', $summary['shipment_lines_updated']],
            ['履歴ロット', $summary['production_lots_updated']],
        ]);

        return self::SUCCESS;
    }
}
