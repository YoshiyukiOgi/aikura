<?php

namespace App\Services;

use App\Models\AccessMigrationBatch;
use App\Models\AccessMigrationTable;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;
use SplFileObject;
use Throwable;

class AccessMigrationPackageStager
{
    public function stage(string $packagePath): AccessMigrationBatch
    {
        $packagePath = realpath($packagePath) ?: throw new RuntimeException('移行パッケージが見つかりません。');
        $manifestPath = $packagePath.DIRECTORY_SEPARATOR.'manifest.json';
        $manifest = $this->readManifest($manifestPath);
        $this->validateManifest($manifest);

        $source = $manifest['source'];
        $batch = AccessMigrationBatch::query()->create([
            'status' => 'staging',
            'source_file_name' => $source['file_name'],
            'source_file_path' => $source['path'],
            'source_sha256' => strtoupper($source['sha256']),
            'source_size' => $source['size'],
            'source_last_modified_at' => $source['last_modified_at'] ?? null,
            'extractor_version' => $manifest['extractor_version'],
            'package_version' => $manifest['package_version'],
            'source_table_count' => $manifest['table_count'],
            'source_row_count' => $manifest['row_count'],
            'manifest' => $manifest,
            'started_at' => now(),
        ]);

        try {
            DB::transaction(function () use ($batch, $manifest, $packagePath): void {
                $manifestTables = collect($manifest['tables'])->keyBy('name');
                $configuredTables = config('access_migration.tables', []);

                foreach ($configuredTables as $tableName => $settings) {
                    $tableManifest = $manifestTables->get($tableName);
                    if ($tableManifest === null) {
                        if ($settings['required'] ?? false) {
                            throw new RuntimeException("必須テーブルがありません: {$tableName}");
                        }

                        continue;
                    }

                    $this->stageTable($batch, $packagePath, $tableManifest, $settings);
                }

                $batch->refresh();
                $batch->update([
                    'status' => 'staged',
                    'staged_table_count' => $batch->tables()->count(),
                    'staged_row_count' => $batch->tables()->sum('staged_row_count'),
                    'completed_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            $batch->update([
                'status' => 'failed',
                'error_count' => 1,
                'failure_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            throw $exception;
        }

        DB::statement('ANALYZE access_migration_staging_rows');

        return $batch->refresh();
    }

    private function stageTable(
        AccessMigrationBatch $batch,
        string $packagePath,
        array $manifest,
        array $settings,
    ): void {
        $fileName = basename((string) $manifest['file']);
        if ($fileName !== $manifest['file']) {
            throw new RuntimeException("不正なパッケージファイル名です: {$manifest['file']}");
        }

        $filePath = realpath($packagePath.DIRECTORY_SEPARATOR.$fileName);
        if ($filePath === false || ! str_starts_with($filePath, $packagePath.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException("テーブルファイルが見つかりません: {$fileName}");
        }

        $actualHash = strtoupper(hash_file('sha256', $filePath));
        if (! hash_equals(strtoupper($manifest['sha256']), $actualHash)) {
            throw new RuntimeException("テーブルファイルのハッシュが一致しません: {$manifest['name']}");
        }

        $table = AccessMigrationTable::query()->create([
            'batch_id' => $batch->id,
            'source_table' => $manifest['name'],
            'package_file' => $fileName,
            'package_sha256' => $actualHash,
            'source_row_count' => $manifest['row_count'],
            'source_columns' => $manifest['columns'],
            'status' => 'staging',
        ]);

        $file = new SplFileObject($filePath, 'rb');
        $rows = [];
        $rowNumber = 0;
        $timestamp = now();

        while (! $file->eof()) {
            $line = $file->fgets();
            if ($line === false || trim($line) === '') {
                continue;
            }

            $rowNumber++;
            try {
                $payload = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException("JSON解析エラー: {$manifest['name']} {$rowNumber}行目", 0, $exception);
            }

            if (! is_array($payload)) {
                throw new RuntimeException("行データがオブジェクトではありません: {$manifest['name']} {$rowNumber}行目");
            }

            $rows[] = [
                'batch_id' => $batch->id,
                'source_table' => $manifest['name'],
                'source_row_number' => $rowNumber,
                'source_key' => $this->sourceKey($payload, $settings['source_key'] ?? [], $manifest['name'], $rowNumber),
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'payload_sha256' => strtoupper(hash('sha256', rtrim($line, "\r\n"))),
                'status' => 'staged',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];

            if (count($rows) >= 500) {
                DB::table('access_migration_staging_rows')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('access_migration_staging_rows')->insert($rows);
        }

        if ($rowNumber !== (int) $manifest['row_count']) {
            throw new RuntimeException(
                "行数が一致しません: {$manifest['name']} (manifest={$manifest['row_count']}, actual={$rowNumber})"
            );
        }

        $table->update(['staged_row_count' => $rowNumber, 'status' => 'staged']);
    }

    private function sourceKey(array $payload, array $fields, string $table, int $rowNumber): string
    {
        if ($fields === []) {
            return (string) $rowNumber;
        }

        $values = [];
        foreach ($fields as $field) {
            if (! array_key_exists($field, $payload) || $payload[$field] === null || $payload[$field] === '') {
                throw new RuntimeException("移行元キーが空です: {$table} {$rowNumber}行目 {$field}");
            }
            $values[] = (string) $payload[$field];
        }

        return implode('|', $values);
    }

    private function readManifest(string $manifestPath): array
    {
        if (! is_file($manifestPath)) {
            throw new RuntimeException('manifest.jsonが見つかりません。');
        }

        try {
            $manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('manifest.jsonを解析できません。', 0, $exception);
        }

        return is_array($manifest) ? $manifest : throw new RuntimeException('manifest.jsonの形式が不正です。');
    }

    private function validateManifest(array $manifest): void
    {
        foreach (['package_version', 'extractor_version', 'source', 'table_count', 'row_count', 'tables'] as $field) {
            if (! array_key_exists($field, $manifest)) {
                throw new RuntimeException("manifest.jsonに{$field}がありません。");
            }
        }

        if ((int) $manifest['package_version'] !== (int) config('access_migration.package_version')) {
            throw new RuntimeException('未対応の移行パッケージバージョンです。');
        }

        foreach (['path', 'file_name', 'size', 'sha256'] as $field) {
            if (! array_key_exists($field, $manifest['source'])) {
                throw new RuntimeException("manifest.jsonのsource.{$field}がありません。");
            }
        }

        if (count($manifest['tables']) !== (int) $manifest['table_count']) {
            throw new RuntimeException('manifest.jsonのテーブル数が一致しません。');
        }
    }
}
