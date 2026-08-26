<?php

namespace Tests\Feature;

use App\Models\AccessMigrationBatch;
use App\Models\Customer;
use App\Services\ImportAccessReceivables;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportAccessCutoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_separates_opening_receivable_history_from_post_cutover_payments_idempotently(): void
    {
        $this->seed(DatabaseSeeder::class);
        $customer = Customer::query()->create([
            'customer_code' => 'ITARO-C-CUTOVER',
            'name' => '開始日テスト得意先',
            'transaction_category_id' => DB::table('transaction_categories')->where('code', 'wholesale')->value('id'),
            'settlement_receivable_category_id' => DB::table('settlement_receivable_categories')->where('code', 'accounts_receivable_1')->value('id'),
            'billing_cycle_id' => DB::table('billing_cycles')->where('code', 'monthly_end_next_month_end')->value('id'),
        ]);
        $batch = AccessMigrationBatch::query()->create([
            'status' => 'inventory_history_imported',
            'source_file_name' => 'Itaro-xp.accdb',
            'source_file_path' => 'C:\\source\\Itaro-xp.accdb',
            'source_sha256' => str_repeat('F', 64),
            'source_size' => 123,
            'extractor_version' => 'test',
            'package_version' => 1,
            'source_table_count' => 3,
            'source_row_count' => 4,
            'manifest' => [],
            'started_at' => now(),
        ]);
        DB::table('access_migration_mappings')->insert([
            'batch_id' => $batch->id,
            'source_table' => '取引先マスター',
            'source_key' => '1',
            'target_table' => 'customers',
            'target_id' => (string) $customer->id,
            'action' => 'imported',
            'source_payload_sha256' => str_repeat('A', 64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->insertSourceRow($batch, '出荷伝票・取引先', '10', [
            '伝票番号' => 10,
            '取引先ID' => 1,
            '年月日' => '2026-06-30',
            '合計' => 100,
        ]);
        $this->insertSourceRow($batch, '出荷伝票・取引先', '11', [
            '伝票番号' => 11,
            '取引先ID' => 1,
            '年月日' => '2026-07-01',
            '合計' => 200,
        ]);
        $this->insertSourceRow($batch, '入金', '20', [
            'ID' => 20,
            '取引先ID' => 1,
            '年月日' => '2026-06-30',
            '金額' => -30,
            '前月分請求' => false,
            '振込料' => false,
        ]);
        $this->insertSourceRow($batch, '入金', '21', [
            'ID' => 21,
            '取引先ID' => 1,
            '年月日' => '2026-07-02',
            '金額' => -40,
            '前月分請求' => false,
            '振込料' => false,
        ]);

        $summary = app(ImportAccessReceivables::class)->import(
            $batch,
            cutoverDate: '2026-07-01',
            openingDate: '2026-06-30',
        );
        $summaryAgain = app(ImportAccessReceivables::class)->import(
            $batch->refresh(),
            cutoverDate: '2026-07-01',
            openingDate: '2026-06-30',
        );

        $this->assertSame(1, $summary['ledger_entries']);
        $this->assertSame(1, $summary['payments']);
        $this->assertSame('70.00', $summary['opening_balance_total']);
        $this->assertSame(1, $summaryAgain['ledger_entries']);
        $this->assertSame(1, $summaryAgain['payments']);
        $this->assertSame('70.00', $summaryAgain['opening_balance_total']);
        $this->assertDatabaseCount('access_receivable_ledger_entries', 1);
        $this->assertDatabaseHas('access_receivable_ledger_entries', ['legacy_access_payment_id' => '21']);
        $this->assertDatabaseMissing('access_receivable_ledger_entries', ['legacy_access_payment_id' => '20']);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('opening_receivable_balances', [
            'customer_id' => $customer->id,
            'as_of_date' => '2026-06-30 00:00:00',
            'opening_balance_amount' => '70.00',
        ]);
    }

    private function insertSourceRow(AccessMigrationBatch $batch, string $table, string $key, array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        DB::table('access_migration_staging_rows')->insert([
            'batch_id' => $batch->id,
            'source_table' => $table,
            'source_row_number' => DB::table('access_migration_staging_rows')->where('batch_id', $batch->id)->count() + 1,
            'source_key' => $key,
            'payload' => $json,
            'payload_sha256' => strtoupper(hash('sha256', $json)),
            'status' => 'staged',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
