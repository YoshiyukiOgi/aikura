<?php

namespace App\Console\Commands;

use App\Models\AccessMigrationBatch;
use App\Services\InitializeAccessCutover;
use Illuminate\Console\Command;
use Throwable;

class InitializeAccessCutoverCommand extends Command
{
    protected $signature = 'aikura:access-cutover-initialize
        {batch : 検証済みAccess移行バッチID}
        {--cutover= : 業務データを取り込む開始日}
        {--opening-date= : 開始売掛の基準日（開始日の前日）}
        {--stock-as-of= : Itaro在庫を登録する基準日}
        {--commit : 初期化と移行を実行する}';

    protected $description = '卸売業務データを白紙化し、指定日以降のItaroデータと開始残高で初期化する。';

    public function handle(InitializeAccessCutover $service): int
    {
        $batch = AccessMigrationBatch::query()->find($this->argument('batch'));
        if ($batch === null) {
            $this->error('指定されたAccess移行バッチが見つかりません。');

            return self::FAILURE;
        }

        foreach (['cutover', 'opening-date', 'stock-as-of'] as $option) {
            if (! $this->option($option)) {
                $this->error("--{$option} を指定してください。");

                return self::FAILURE;
            }
        }

        try {
            $result = $this->option('commit')
                ? $service->initialize(
                    $batch,
                    (string) $this->option('cutover'),
                    (string) $this->option('opening-date'),
                    (string) $this->option('stock-as-of'),
                )
                : $service->preview(
                    $batch,
                    (string) $this->option('cutover'),
                    (string) $this->option('opening-date'),
                    (string) $this->option('stock-as-of'),
                );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($result['committed'] ? '初期化とItaro移行が完了しました。' : '初期化ドライランです。データは変更していません。');
        $this->table(['項目', '値'], [
            ['バッチID', $result['batch_id']],
            ['取引開始日', $result['cutover_date']],
            ['開始売掛日', $result['opening_date']],
            ['在庫基準日', $result['stock_as_of_date']],
            ['削除対象行数', array_sum($result['delete_counts'])],
        ]);

        if (! $result['committed']) {
            $this->table(
                ['削除対象テーブル', '現在件数'],
                collect($result['delete_counts'])->map(fn (int $count, string $table): array => [$table, $count])->values()->all(),
            );
            $this->warn('実行する場合は同じコマンドへ --commit を追加します。');

            return self::SUCCESS;
        }

        $this->table(
            ['検査', '期待', '実績', '結果'],
            collect($result['checks'])->map(fn (array $check, string $name): array => [
                $name,
                $check['expected'],
                $check['actual'],
                $check['passed'] ? '一致' : '不一致',
            ])->values()->all(),
        );

        return self::SUCCESS;
    }
}
