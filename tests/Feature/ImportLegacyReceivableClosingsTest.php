<?php

namespace Tests\Feature;

use App\Models\AccessMigrationBatch;
use App\Models\Customer;
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
        $path = storage_path('framework/testing/legacy-paper-balances.csv');
        file_put_contents($path, implode("\n", [
            'printed_customer_name,may_end_balance,customer_code',
            'Paper name,1000,LEGACY-CUSTOMER-1',
        ]));

        $service = app(ImportLegacyReceivableClosings::class);
        $may = $service->importStatement($path, 2026, 5, '1000.00', true);
        $june = $service->projectMonthFromAccess($batch->id, 2026, 6, true);

        $this->assertSame('1000.00', $may['total']);
        $this->assertSame('830.00', $june['total']);
        $this->assertSame('330.00', $june['shipment_total']);
        $this->assertSame('500.00', $june['receipts_and_fees_total']);
        $this->assertDatabaseHas('receivable_monthly_balances', [
            'year' => 2026,
            'month' => 5,
            'customer_id' => $customer->id,
            'status' => 'closed',
            'outstanding_amount' => 1000,
        ]);
        $juneBalance = ReceivableMonthlyBalance::query()->where('year', 2026)->where('month', 6)->sole();
        $this->assertSame('1330.00', $juneBalance->scheduled_amount);
        $this->assertSame('500.00', $juneBalance->received_amount);
        $this->assertSame('830.00', $juneBalance->outstanding_amount);
    }
}
