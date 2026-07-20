<?php

namespace App\Console\Commands;

use App\Models\AccessMigrationBatch;
use App\Services\ImportAccessShipments;
use Illuminate\Console\Command;
use Throwable;

class ImportAccessShipmentsCommand extends Command
{
    protected $signature = 'aikura:access-import-shipments {batch : マスタ移行済みAccess移行バッチID}';

    protected $description = 'Import Access shipment history without creating inventory movements.';

    public function handle(ImportAccessShipments $importer): int
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

        $this->info('Access出荷履歴移行が完了しました。');
        $this->table(['対象', '件数'], [
            ['出荷ヘッダー', $summary['shipment_headers']],
            ['出荷明細', $summary['shipment_lines']],
            ['要税務確認', $summary['tax_review_headers']],
            ['在庫移動作成', $summary['stock_movements_created']],
        ]);

        return self::SUCCESS;
    }
}
