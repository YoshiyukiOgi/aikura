<?php

namespace Tests\Feature;

use App\Models\AccessMigrationBatch;
use App\Models\Customer;
use App\Models\OpeningReceivableBalance;
use App\Models\Payment;
use App\Models\ShipmentHeader;
use App\Services\Billing\CreateReceivableMonthlyBalanceDraftService;
use App\Services\Billing\ReceivableBalanceService;
use App\Services\ImportAccessReceivables;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportAccessReceivablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_access_ledger_payments_and_opening_receivables_idempotently(): void
    {
        $this->seed(DatabaseSeeder::class);
        $customer = $this->createCustomer();
        $batch = AccessMigrationBatch::query()->create([
            'status' => 'inventory_history_imported',
            'source_file_name' => 'Itaro-xp.accdb',
            'source_file_path' => 'C:\\source\\Itaro-xp.accdb',
            'source_sha256' => str_repeat('C', 64),
            'source_size' => 123,
            'source_last_modified_at' => '2026-07-19 01:42:39+00',
            'extractor_version' => 'test',
            'package_version' => 1,
            'source_table_count' => 1,
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
            'source_payload_sha256' => str_repeat('D', 64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->insertPaymentSource($batch, '1', 100, true, false, null);
        $this->insertPaymentSource($batch, '2', -60, false, false, 'PayPay銀行');
        $this->insertPaymentSource($batch, '3', -5, false, true, '振込料');
        $this->insertPaymentSource($batch, '4', 20, false, false, '調整');
        DB::table('access_migration_staging_rows')->insert([
            'batch_id' => $batch->id,
            'source_table' => '空容器伝票-取引先',
            'source_row_number' => 5,
            'source_key' => '500',
            'payload' => json_encode([
                '伝票番号' => 500,
                '取引先ID' => 1,
                '年月日' => '2026-07-01T00:00:00.0000000',
                '合計' => 25,
            ], JSON_UNESCAPED_UNICODE),
            'payload_sha256' => str_repeat('F', 64),
            'status' => 'staged',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        ShipmentHeader::query()->create([
            'document_number' => 'ITARO-S-TEST-1',
            'status' => 'confirmed',
            'customer_id' => $customer->id,
            'transaction_category_id' => $customer->transaction_category_id,
            'settlement_receivable_category_id' => $customer->settlement_receivable_category_id,
            'billing_cycle_id' => $customer->billing_cycle_id,
            'document_date' => '2026-07-10',
            'legacy_access_document_number' => 'TEST-1',
            'legacy_access_net_amount' => 190,
            'legacy_access_consumption_tax_amount' => 10,
            'legacy_access_total_amount' => 200,
        ]);
        ShipmentHeader::query()->create([
            'document_number' => 'ITARO-S-FUTURE',
            'status' => 'confirmed',
            'customer_id' => $customer->id,
            'transaction_category_id' => $customer->transaction_category_id,
            'settlement_receivable_category_id' => $customer->settlement_receivable_category_id,
            'billing_cycle_id' => $customer->billing_cycle_id,
            'document_date' => '2026-07-20',
            'legacy_access_document_number' => 'FUTURE',
            'legacy_access_total_amount' => 999,
        ]);

        $summary = app(ImportAccessReceivables::class)->import($batch);
        $summaryAgain = app(ImportAccessReceivables::class)->import($batch->refresh());

        $this->assertSame(4, $summary['ledger_entries']);
        $this->assertSame(2, $summary['payments']);
        $this->assertSame('230.00', $summary['opening_balance_total']);
        $this->assertSame('2026-07-19', $summary['as_of_date']);
        $this->assertSame($summary['opening_balance_total'], $summaryAgain['opening_balance_total']);
        $this->assertDatabaseCount('access_receivable_ledger_entries', 4);
        $this->assertDatabaseCount('payments', 2);
        $this->assertSame(['5.00', '60.00'], Payment::query()->orderBy('amount')->pluck('amount')->all());
        $this->assertTrue(Payment::query()->where('payment_method', 'paypay_bank')->firstOrFail()->is_legacy_history);

        $opening = OpeningReceivableBalance::query()->firstOrFail();
        $this->assertSame('200.00', $opening->source_sales_amount);
        $this->assertSame('55.00', $opening->source_ledger_amount);
        $this->assertSame('25.00', $opening->source_container_amount);
        $this->assertSame(1, $opening->source_container_entry_count);
        $this->assertSame('230.00', $opening->opening_balance_amount);
        $this->assertSame('230.00', app(ReceivableBalanceService::class)->forCustomer($customer)->outstandingAmount);

        $monthly = app(CreateReceivableMonthlyBalanceDraftService::class)->create(2026, 7, 'migration test');
        $this->assertSame('230.00', $monthly->firstWhere('customer_id', $customer->id)->outstanding_amount);
        $this->assertSame('receivables_imported', $batch->refresh()->status);

        $deltaBatch = AccessMigrationBatch::query()->create([
            'status' => 'inventory_history_imported',
            'baseline_batch_id' => $batch->id,
            'source_file_name' => 'Itaro-xp.accdb',
            'source_file_path' => 'C:\\source\\Itaro-xp.accdb',
            'source_sha256' => str_repeat('E', 64),
            'source_size' => 124,
            'source_last_modified_at' => '2026-07-20 01:42:39+00',
            'extractor_version' => 'test',
            'package_version' => 1,
            'source_table_count' => 1,
            'source_row_count' => 2,
            'manifest' => [],
            'started_at' => now(),
        ]);
        DB::table('access_migration_mappings')->insert([
            'batch_id' => $deltaBatch->id,
            'source_table' => '取引先マスター',
            'source_key' => '1',
            'target_table' => 'customers',
            'target_id' => (string) $customer->id,
            'action' => 'imported',
            'source_payload_sha256' => str_repeat('D', 64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->insertPaymentSource($deltaBatch, '2', -60, false, false, 'PayPay銀行');
        $this->insertPaymentSource($deltaBatch, '5', -25, false, false, '追加入金');
        $deltaRows = DB::table('access_migration_staging_rows')
            ->where('batch_id', $deltaBatch->id)
            ->pluck('id', 'source_key');
        DB::table('access_migration_deltas')->insert([
            [
                'batch_id' => $deltaBatch->id,
                'baseline_batch_id' => $batch->id,
                'source_table' => '入金',
                'source_key' => '2',
                'change_type' => 'unchanged',
                'current_staging_row_id' => $deltaRows['2'],
                'current_payload_sha256' => str_repeat('A', 64),
                'apply_status' => 'planned',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'batch_id' => $deltaBatch->id,
                'baseline_batch_id' => $batch->id,
                'source_table' => '入金',
                'source_key' => '5',
                'change_type' => 'new',
                'current_staging_row_id' => $deltaRows['5'],
                'current_payload_sha256' => str_repeat('B', 64),
                'apply_status' => 'planned',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $deltaSummary = app(ImportAccessReceivables::class)->import($deltaBatch, true);

        $this->assertSame(1, $deltaSummary['ledger_entries']);
        $this->assertSame(1, $deltaSummary['payments']);
        $this->assertSame(0, $deltaSummary['opening_balances']);
        $this->assertNull($deltaSummary['as_of_date']);
        $this->assertDatabaseCount('access_receivable_ledger_entries', 5);
        $this->assertDatabaseCount('payments', 3);
        $this->assertDatabaseCount('opening_receivable_balances', 1);
    }

    private function createCustomer(): Customer
    {
        return Customer::query()->create([
            'customer_code' => 'ITARO-C-0001',
            'name' => '移行売掛得意先',
            'transaction_category_id' => DB::table('transaction_categories')->where('code', 'wholesale')->value('id'),
            'settlement_receivable_category_id' => DB::table('settlement_receivable_categories')->where('code', 'accounts_receivable_1')->value('id'),
            'billing_cycle_id' => DB::table('billing_cycles')->where('code', 'monthly_end_next_month_end')->value('id'),
        ]);
    }

    private function insertPaymentSource(
        AccessMigrationBatch $batch,
        string $key,
        int $amount,
        bool $previousMonthBill,
        bool $transferFee,
        ?string $description,
    ): void {
        $payload = [
            'ID' => (int) $key,
            '取引先ID' => 1,
            '年月日' => '2026-07-15T00:00:00.0000000',
            '請求年' => 2026,
            '請求月' => 7,
            '金額' => $amount,
            '前月分請求' => $previousMonthBill,
            '振込料' => $transferFee,
            '摘要' => $description,
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        DB::table('access_migration_staging_rows')->insert([
            'batch_id' => $batch->id,
            'source_table' => '入金',
            'source_row_number' => (int) $key,
            'source_key' => $key,
            'payload' => $json,
            'payload_sha256' => strtoupper(hash('sha256', $json)),
            'status' => 'staged',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
