<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\SettlementReceivableCategory;
use App\Models\TransactionCategory;
use Database\Seeders\CustomerMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_master_tables_exist(): void
    {
        foreach ([
            'transaction_categories',
            'settlement_receivable_categories',
            'billing_cycles',
            'customers',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table [{$table}] does not exist.");
        }
    }

    public function test_customers_table_has_required_columns(): void
    {
        foreach ([
            'customer_code',
            'name',
            'name_kana',
            'short_name',
            'billing_name',
            'transaction_category_id',
            'settlement_receivable_category_id',
            'billing_cycle_id',
            'tax_rounding_method',
            'tax_calculation_unit',
            'amount_rounding_method',
            'invoice_required',
            'search_key',
            'search_key_normalized',
            'legacy_code',
            'is_active',
            'disabled_at',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('customers', $column), "Column [customers.{$column}] does not exist.");
        }
    }

    public function test_customer_master_seed_creates_default_categories(): void
    {
        $this->seed(CustomerMasterSeeder::class);

        $this->assertDatabaseHas('transaction_categories', [
            'code' => 'wholesale',
            'name' => '卸価格',
        ]);

        $this->assertDatabaseHas('settlement_receivable_categories', [
            'code' => 'accounts_receivable_1',
            'name' => '売掛金1',
        ]);

        $this->assertDatabaseHas('billing_cycles', [
            'code' => 'monthly_end_next_month_end',
            'billing_method' => 'monthly_closing',
        ]);
    }

    public function test_customer_belongs_to_required_business_categories(): void
    {
        $this->seed(CustomerMasterSeeder::class);

        $customer = Customer::create([
            'customer_code' => 'CUST001',
            'name' => '山田酒店',
            'name_kana' => 'ヤマダサケテン',
            'short_name' => '山田',
            'transaction_category_id' => TransactionCategory::where('code', 'wholesale')->firstOrFail()->id,
            'settlement_receivable_category_id' => SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail()->id,
            'billing_cycle_id' => BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail()->id,
            'search_key' => 'CUST001 山田酒店 ヤマダサケテン 山田',
        ]);

        $this->assertSame('cust001山田酒店ヤマダサケテン山田', $customer->search_key_normalized);
        $this->assertSame('卸価格', $customer->transactionCategory->name);
        $this->assertSame('売掛金1', $customer->settlementReceivableCategory->name);
        $this->assertSame('月末締 翌月末入金', $customer->billingCycle->name);
        $this->assertTrue($customer->refresh()->is_active);
    }

    public function test_customer_can_be_disabled_without_deleting_history_target(): void
    {
        $this->seed(CustomerMasterSeeder::class);

        $customer = Customer::create([
            'customer_code' => 'CUST002',
            'name' => '無効化対象',
            'transaction_category_id' => TransactionCategory::where('code', 'retail')->firstOrFail()->id,
            'settlement_receivable_category_id' => SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail()->id,
            'billing_cycle_id' => BillingCycle::where('code', 'cash_immediate')->firstOrFail()->id,
        ]);

        $customer->update([
            'is_active' => false,
            'disabled_at' => now(),
        ]);

        $this->assertFalse($customer->refresh()->is_active);
        $this->assertNotNull($customer->disabled_at);
        $this->assertDatabaseHas('customers', [
            'customer_code' => 'CUST002',
            'is_active' => false,
        ]);
    }
}
