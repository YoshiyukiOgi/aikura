<?php

namespace App\Console\Commands;

use App\Services\AccessMigrationPackageStager;
use App\Services\AccessMigrationValidator;
use Illuminate\Console\Command;
use Throwable;

class StageAccessMigrationPackage extends Command
{
    protected $signature = 'aikura:access-stage
        {package : manifest.jsonを含む移行パッケージディレクトリ}
        {--validate : ステージング後に全件検証する}';

    protected $description = 'Verify and atomically stage a read-only Access migration package.';

    public function handle(AccessMigrationPackageStager $stager, AccessMigrationValidator $validator): int
    {
        $path = (string) $this->argument('package');
        if (! str_starts_with($path, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            $path = base_path($path);
        }

        try {
            $batch = $stager->stage($path);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Access移行パッケージをステージングしました。batch_id={$batch->id}");
        $this->table(['元テーブル', '元行数', '取込行数'], $batch->tables()
            ->orderBy('id')
            ->get()
            ->map(fn ($table) => [$table->source_table, $table->source_row_count, $table->staged_row_count])
            ->all());
        $this->line("原本SHA-256: {$batch->source_sha256}");
        $this->line("対象: {$batch->staged_table_count}テーブル / {$batch->staged_row_count}行");

        if ($this->option('validate')) {
            $batch = $validator->validate($batch);
            $this->line("検証状態: {$batch->status} (エラー {$batch->error_count} / 警告 {$batch->warning_count})");

            return $batch->error_count > 0 ? self::FAILURE : self::SUCCESS;
        }

        return self::SUCCESS;
    }
}
