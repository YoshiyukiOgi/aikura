<?php

namespace Tests\Feature;

use App\Models\AccessMigrationBatch;
use App\Services\AccessMigrationPackageStager;
use App\Services\AccessMigrationValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class AccessMigrationTest extends TestCase
{
    use RefreshDatabase;

    private string $packagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->packagePath = storage_path('framework/testing/access-migration-package');
        File::deleteDirectory($this->packagePath);
        File::ensureDirectoryExists($this->packagePath);

        config()->set('access_migration.tables', [
            '取引先マスター' => ['source_key' => ['取引先ID'], 'required' => true],
            '商品マスター' => ['source_key' => ['商品ID'], 'required' => true],
            '出荷伝票・取引先' => ['source_key' => ['伝票番号'], 'required' => true],
            '出荷伝票・商品' => ['source_key' => ['ID'], 'required' => true],
            '酒税区分' => ['source_key' => ['ID'], 'required' => true],
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->packagePath);
        parent::tearDown();
    }

    public function test_it_stages_and_validates_a_package_without_recalculating_source_values(): void
    {
        $this->writePackage([
            '取引先マスター' => [['取引先ID' => 1, '取引先名' => '得意先']],
            '商品マスター' => [['商品ID' => 10, '商品名' => '清酒']],
            '酒税区分' => [[
                'ID' => 1,
                '戻入取引' => false,
                '酒税未納取引' => false,
                '輸出取引' => false,
            ]],
            '出荷伝票・取引先' => [[
                '伝票番号' => 100,
                '年月日' => '2099-01-01T00:00:00.0000000',
                '取引先ID' => 1,
                '金額' => 100,
                '酒税区分' => 1,
                '戻入取引' => false,
                '酒税未納取引' => false,
                '輸出取引' => false,
            ]],
            '出荷伝票・商品' => [[
                'ID' => 1,
                '伝票番号' => 100,
                '商品ID' => 10,
                '個数' => -1,
                '取引額' => 90,
                '酒税' => 100000,
                '軽減率' => 80,
            ]],
        ]);

        $batch = app(AccessMigrationPackageStager::class)->stage($this->packagePath);

        $this->assertSame('staged', $batch->status);
        $this->assertSame(5, $batch->staged_table_count);
        $this->assertSame(5, $batch->staged_row_count);
        $this->assertDatabaseHas('access_migration_staging_rows', [
            'batch_id' => $batch->id,
            'source_table' => '出荷伝票・商品',
            'source_key' => '1',
        ]);

        $batch = app(AccessMigrationValidator::class)->validate($batch);

        $this->assertSame('ready_with_warnings', $batch->status);
        $this->assertSame(0, $batch->error_count);
        $this->assertSame(3, $batch->warning_count);
        $this->assertDatabaseHas('access_migration_issues', [
            'batch_id' => $batch->id,
            'issue_code' => 'negative_quantity',
        ]);

        $payload = DB::table('access_migration_staging_rows')
            ->where('batch_id', $batch->id)
            ->where('source_table', '出荷伝票・商品')
            ->value('payload');
        $payload = is_string($payload) ? json_decode($payload, true) : (array) $payload;
        $this->assertSame(100000, $payload['酒税']);
        $this->assertSame(80, $payload['軽減率']);
    }

    public function test_a_hash_error_leaves_no_partially_staged_rows(): void
    {
        $this->writePackage([
            '取引先マスター' => [['取引先ID' => 1]],
            '商品マスター' => [['商品ID' => 10]],
            '酒税区分' => [['ID' => 1]],
            '出荷伝票・取引先' => [['伝票番号' => 100]],
            '出荷伝票・商品' => [['ID' => 1]],
        ]);

        $manifest = json_decode(File::get($this->packagePath.'/manifest.json'), true);
        $manifest['tables'][2]['sha256'] = str_repeat('0', 64);
        File::put($this->packagePath.'/manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE));

        try {
            app(AccessMigrationPackageStager::class)->stage($this->packagePath);
            $this->fail('Expected staging to fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('ハッシュが一致しません', $exception->getMessage());
        }

        $this->assertSame('failed', AccessMigrationBatch::query()->sole()->status);
        $this->assertDatabaseCount('access_migration_staging_rows', 0);
        $this->assertDatabaseCount('access_migration_tables', 0);
    }

    private function writePackage(array $tables): void
    {
        $manifestTables = [];
        $totalRows = 0;

        foreach (array_values($tables) as $index => $rows) {
            $name = array_keys($tables)[$index];
            $file = sprintf('%03d.ndjson', $index + 1);
            $contents = collect($rows)
                ->map(fn (array $row) => json_encode($row, JSON_UNESCAPED_UNICODE))
                ->implode("\n")."\n";
            File::put($this->packagePath.'/'.$file, $contents);
            $manifestTables[] = [
                'name' => $name,
                'file' => $file,
                'row_count' => count($rows),
                'sha256' => strtoupper(hash('sha256', $contents)),
                'columns' => [],
            ];
            $totalRows += count($rows);
        }

        File::put($this->packagePath.'/manifest.json', json_encode([
            'package_version' => 1,
            'extractor_version' => 'test',
            'source' => [
                'path' => 'C:\\migration\\source.accdb',
                'file_name' => 'source.accdb',
                'size' => 123,
                'last_modified_at' => '2026-07-19T00:00:00Z',
                'sha256' => str_repeat('A', 64),
            ],
            'table_count' => count($manifestTables),
            'row_count' => $totalRows,
            'tables' => $manifestTables,
        ], JSON_UNESCAPED_UNICODE));
    }
}
