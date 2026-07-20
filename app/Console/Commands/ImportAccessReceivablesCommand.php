<?php

namespace App\Console\Commands;

use App\Models\AccessMigrationBatch;
use App\Services\ImportAccessReceivables;
use Illuminate\Console\Command;
use Throwable;

class ImportAccessReceivablesCommand extends Command
{
    protected $signature = 'aikura:access-import-receivables {batch : 在庫履歴移行済みAccess移行バッチID}';

    protected $description = 'Import Access receivable ledger, payment history, and calculated opening receivable balances.';

    public function handle(ImportAccessReceivables $importer): int
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

        $this->info('Access入金履歴・開始売掛残高の移行が完了しました。');
        $this->table(['対象', '値'], [
            ['Access符号付き台帳', $summary['ledger_entries']],
            ['入金・振込料履歴', $summary['payments']],
            ['開始売掛対象得意先', $summary['opening_balances']],
            ['残高あり得意先', $summary['non_zero_opening_balances']],
            ['開始売掛合計', $summary['opening_balance_total']],
            ['基準日', $summary['as_of_date']],
        ]);

        return self::SUCCESS;
    }
}
