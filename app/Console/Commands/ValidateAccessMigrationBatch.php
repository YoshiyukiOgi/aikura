<?php

namespace App\Console\Commands;

use App\Models\AccessMigrationBatch;
use App\Services\AccessMigrationValidator;
use Illuminate\Console\Command;
use Throwable;

class ValidateAccessMigrationBatch extends Command
{
    protected $signature = 'aikura:access-validate {batch : Access移行バッチID}';

    protected $description = 'Validate staged Access rows and report blocking errors and preserved warnings.';

    public function handle(AccessMigrationValidator $validator): int
    {
        $batch = AccessMigrationBatch::query()->find($this->argument('batch'));
        if ($batch === null) {
            $this->error('Access移行バッチが見つかりません。');

            return self::FAILURE;
        }

        try {
            $batch = $validator->validate($batch);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['重大度', 'コード', '件数', '内容'], collect($batch->validation_summary['checks'] ?? [])
            ->filter(fn (array $check) => $check['count'] > 0)
            ->map(fn (array $check) => [$check['severity'], $check['code'], $check['count'], $check['message']])
            ->all());
        $this->line("状態: {$batch->status}");
        $this->line("エラー: {$batch->error_count} / 警告件数（重複を含む）: {$batch->warning_count}");

        return $batch->error_count > 0 ? self::FAILURE : self::SUCCESS;
    }
}
