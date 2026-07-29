<?php

namespace Tests\Feature;

use App\Models\AccessMigrationBatch;
use App\Services\ApplyAccessMigrationDelta;
use App\Services\PlanAccessMigrationDelta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class AccessMigrationDeltaTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_plans_new_changed_unchanged_and_deleted_rows(): void
    {
        $baseline = $this->createBatch('completed', 'A');
        $current = $this->createBatch('ready', 'B');

        $this->insertRow($baseline, '商品マスター', '1', ['name' => 'same']);
        $this->insertRow($baseline, '商品マスター', '2', ['name' => 'before']);
        $this->insertRow($baseline, '商品マスター', '3', ['name' => 'deleted']);
        $this->insertRow($current, '商品マスター', '1', ['name' => 'same']);
        $this->insertRow($current, '商品マスター', '2', ['name' => 'after']);
        $this->insertRow($current, '商品マスター', '4', ['name' => 'new']);

        $summary = app(PlanAccessMigrationDelta::class)->plan($current, $baseline);

        $this->assertSame(1, $summary['new']);
        $this->assertSame(1, $summary['changed']);
        $this->assertSame(1, $summary['unchanged']);
        $this->assertSame(1, $summary['deleted']);
        $this->assertSame(1, $summary['blockers']);
        $this->assertFileExists($summary['report_path']);
        $this->assertSame($baseline->id, $current->refresh()->baseline_batch_id);
        $this->assertDatabaseHas('access_migration_deltas', [
            'batch_id' => $current->id,
            'source_table' => '商品マスター',
            'source_key' => '4',
            'change_type' => 'new',
        ]);

        File::delete($summary['report_path']);
    }

    public function test_apply_stops_when_transaction_history_was_changed(): void
    {
        $baseline = $this->createBatch('completed', 'C');
        $current = $this->createBatch('ready', 'D');
        $this->insertRow($baseline, '入金', '10', ['金額' => -100]);
        $this->insertRow($current, '入金', '10', ['金額' => -200]);
        app(PlanAccessMigrationDelta::class)->plan($current, $baseline);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('自動適用できない変更');

        app(ApplyAccessMigrationDelta::class)->apply($current->refresh());
    }

    private function createBatch(string $status, string $hashCharacter): AccessMigrationBatch
    {
        return AccessMigrationBatch::query()->create([
            'status' => $status,
            'source_file_name' => 'Itaro-xp.accdb',
            'source_file_path' => 'C:\\source\\Itaro-xp.accdb',
            'source_sha256' => str_repeat($hashCharacter, 64),
            'source_size' => 123,
            'extractor_version' => 'test',
            'package_version' => 1,
            'source_table_count' => 1,
            'source_row_count' => 3,
            'manifest' => [],
            'started_at' => now(),
            'completed_at' => $status === 'completed' ? now() : null,
        ]);
    }

    private function insertRow(AccessMigrationBatch $batch, string $table, string $key, array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        DB::table('access_migration_staging_rows')->insert([
            'batch_id' => $batch->id,
            'source_table' => $table,
            'source_row_number' => DB::table('access_migration_staging_rows')
                ->where('batch_id', $batch->id)
                ->count() + 1,
            'source_key' => $key,
            'payload' => $json,
            'payload_sha256' => strtoupper(hash('sha256', $json)),
            'status' => 'staged',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
