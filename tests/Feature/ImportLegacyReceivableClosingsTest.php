<?php

namespace Tests\Feature;

use App\Models\AccessMigrationBatch;
use App\Models\Customer;
use App\Models\OpeningReceivableBalance;
use App\Models\ReceivableMonthlyBalance;
use App\Models\ShipmentHeader;
use App\Services\Billing\ImportLegacyReceivableClosings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportLegacyReceivableClosingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_a_paper_close_and_projects_the_next_access_month(): void
    {
        $this->seed(DatabaseSeeder::class);
        $customer = Customer::query()->create([
            'customer_code' => 'LEGACY-CUSTOMER-1',
            'name' => 'Legacy customer',
            'short_name' => 'Paper name',
            'transaction_category_id' => DB::table('transaction_categories')->where('code', 'wholesale')->value('id'),
            'settlement_receivable_category_id' => DB::table('settlement_receivable_categories')->where('code', 'accounts_receivable_1')->value('id'),
            'billing_cycle_id' => DB::table('billing_cycles')->where('code', 'monthly_end_next_month_end')->value('id'),
        ]);
        $batch = AccessMigrationBatch::query()->create([
            'status' => 'receivables_imported',
            'source_file_name' => 'Itaro-xp.accdb',
            'source_file_path' => 'C:\\source\\Itaro-xp.accdb',
            'source_sha256' => str_repeat('A', 64),
            'source_size' => 1,
            'extractor_version' => 'test',
            'package_version' => 1,
            'source_table_count' => 1,
            'source_row_count' => 1,
            'manifest' => [],
            'started_at' => now(),
        ]);
        ShipmentHeader::query()->create([
            'document_number' => 'LEGACY-SHIPMENT-1',
            'status' => 'confirmed',
            'customer_id' => $customer->id,
            'transaction_category_id' => $customer->transaction_category_id,
            'settlement_receivable_category_id' => $customer->settlement_receivable_category_id,
            'billing_cycle_id' => $customer->billing_cycle_id,
            'document_date' => '2026-06-10',
            'legacy_access_document_number' => '100',
            'legacy_access_total_amount' => 330,
        ]);
        DB::table('access_receivable_ledger_entries')->insert([
            'access_migration_batch_id' => $batch->id,
            'customer_id' => $customer->id,
            'legacy_access_payment_id' => '1',
            'entry_date' => '2026-06-20',
            'billing_year' => 2026,
            'billing_month' => 6,
            'signed_amount' => -500,
            'entry_type' => 'receipt',
            'is_previous_month_bill' => false,
            'is_transfer_fee' => false,
            'source_payload' => '{}',
            'source_payload_sha256' => str_repeat('B', 64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('access_migration_mappings')->insert([
            'batch_id' => $batch->id,
            'source_table' => '取引先マスター',
            'source_key' => '1',
            'target_table' => 'customers',
            'target_id' => (string) $customer->id,
            'action' => 'imported',
            'source_payload_sha256' => str_repeat('C', 64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('access_migration_staging_rows')->insert([
            'batch_id' => $batch->id,
            'source_table' => '空容器伝票-取引先',
            'source_row_number' => 1,
            'source_key' => '200',
            'payload' => json_encode([
                '伝票番号' => 200,
                '年月日' => '2026-06-15T00:00:00.0000000',
                '取引先ID' => 1,
                '合計' => 50,
            ], JSON_UNESCAPED_UNICODE),
            'payload_sha256' => str_repeat('D', 64),
            'status' => 'staged',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $path = storage_path('framework/testing/legacy-paper-balances.csv');
        file_put_contents($path, implode("\n", [
            'printed_customer_name,may_end_balance,customer_code',
            'Paper name,1000,LEGACY-CUSTOMER-1',
        ]));

        $service = app(ImportLegacyReceivableClosings::class);
        $may = $service->importStatement($path, 2026, 5, '1000.00', true);
        $june = $service->projectMonthFromAccess($batch->id, 2026, 6, true);
        $staleCustomer = Customer::query()->create([
            'customer_code' => 'LEGACY-CUSTOMER-STALE',
            'name' => 'Stale customer',
            'transaction_category_id' => $customer->transaction_category_id,
            'settlement_receivable_category_id' => $customer->settlement_receivable_category_id,
            'billing_cycle_id' => $customer->billing_cycle_id,
        ]);
        OpeningReceivableBalance::query()->create([
            'access_migration_batch_id' => $batch->id,
            'customer_id' => $staleCustomer->id,
            'status' => 'calculated',
            'as_of_date' => '2026-05-31',
            'calculated_balance_amount' => 999,
            'adjustment_amount' => 0,
            'opening_balance_amount' => 999,
            'calculated_at' => now(),
        ]);
        $carry = $service->carryForwardOpening($batch, 2026, 6, '2026-07-01', '780.00');

        $this->assertSame('1000.00', $may['total']);
        $this->assertSame('780.00', $june['total']);
        $this->assertSame('330.00', $june['shipment_total']);
        $this->assertSame('500.00', $june['receipts_and_fees_total']);
        $this->assertSame('50.00', $june['empty_container_total']);
        $this->assertDatabaseHas('receivable_monthly_balances', [
            'year' => 2026,
            'month' => 5,
            'customer_id' => $customer->id,
            'status' => 'closed',
            'outstanding_amount' => 1000,
        ]);
        $juneBalance = ReceivableMonthlyBalance::query()->where('year', 2026)->where('month', 6)->sole();
        $this->assertSame('1280.00', $juneBalance->scheduled_amount);
        $this->assertSame('500.00', $juneBalance->received_amount);
        $this->assertSame('780.00', $juneBalance->outstanding_amount);
        $this->assertSame('780.00', $carry['total']);
        $opening = OpeningReceivableBalance::query()->sole();
        $this->assertSame('2026-07-01', $opening->as_of_date->toDateString());
        $this->assertSame('reconciled', $opening->status);
        $this->assertSame('780.00', $opening->opening_balance_amount);
        $this->assertDatabaseMissing('opening_receivable_balances', [
            'access_migration_batch_id' => $batch->id,
            'customer_id' => $staleCustomer->id,
        ]);

        $juneDraft = $service->projectMonthFromAccess($batch->id, 2026, 6, true, 'draft');
        $this->assertSame('draft', $juneDraft['status']);
        $this->assertDatabaseHas('receivable_monthly_balances', [
            'year' => 2026,
            'month' => 6,
            'customer_id' => $customer->id,
            'status' => 'draft',
            'confirmed_at' => null,
            'closed_at' => null,
            'outstanding_amount' => 780,
        ]);
    }
}
