<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\InternalBalanceOpening;
use App\Models\InvoiceHeader;
use App\Models\Product;
use App\Models\SettlementReceivableCategory;
use App\Models\ShipmentHeader;
use App\Models\ShipmentLine;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Services\Billing\CloseInternalMonthlyBalanceService;
use App\Services\Billing\ConfirmInternalMonthlyBalanceService;
use App\Services\Billing\ConfirmInvoiceService;
use App\Services\Billing\CreateClosingInvoiceService;
use App\Services\Billing\CreateInternalMonthlyBalanceDraftService;
use App\Services\Billing\RecordInternalBalanceOpeningService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalBillingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_statement_carries_opening_balance_without_creating_external_receivable(): void
    {
        $this->seed(DatabaseSeeder::class);
        AppSetting::setValue('operational_start_date', '2026-07-01');

        $customer = $this->createInternalCustomer();
        app(RecordInternalBalanceOpeningService::class)->record(
            $customer, '2026-07-01', '96939.00',
            '2026年6月末の自家用残高。移行元帳票の自販促95,684円と自研究1,255円の合計。',
        );

        $this->createConfirmedShipment($customer, '2026-07-15', '10000.0000');
        $july = app(CreateClosingInvoiceService::class)->create($customer->id, '2026-07-31', reason: '社内請求テスト');
        $july = app(ConfirmInvoiceService::class)->confirm($july, '社内請求確定テスト');

        $this->assertSame('internal_statement', $july->document_type);
        $this->assertSame('96939.00', $july->previous_balance_amount);
        $this->assertSame('10000.00', $july->current_invoice_amount);
        $this->assertSame('106939.00', $july->total_amount);
        $this->assertDatabaseMissing('payment_schedules', ['invoice_header_id' => $july->id]);

        $julyBalances = app(CreateInternalMonthlyBalanceDraftService::class)->create(2026, 7, '社内残高作成テスト');
        $this->assertCount(1, $julyBalances);
        $this->assertSame('96939.00', $julyBalances->first()->opening_amount);
        $this->assertSame('10000.00', $julyBalances->first()->charge_amount);
        $this->assertSame('106939.00', $julyBalances->first()->closing_amount);
        app(ConfirmInternalMonthlyBalanceService::class)->confirm(2026, 7, '社内残高確定テスト');
        app(CloseInternalMonthlyBalanceService::class)->close(2026, 7, '社内残高締めテスト');

        $this->createConfirmedShipment($customer, '2026-08-10', '20000.0000');
        $august = app(CreateClosingInvoiceService::class)->create($customer->id, '2026-08-31', reason: '翌月社内請求テスト');
        $august = app(ConfirmInvoiceService::class)->confirm($august, '翌月社内請求確定テスト');
        $this->assertSame('106939.00', $august->previous_balance_amount);
        $this->assertSame('126939.00', $august->total_amount);
        $this->assertDatabaseCount('payment_schedules', 0);

        $this->assertSame(2, InvoiceHeader::query()->where('document_type', 'internal_statement')->count());
    }

    private function createInternalCustomer(): Customer
    {
        return Customer::create([
            'customer_code' => 'INTERNAL-TEST-001',
            'name' => '社内販売促進費',
            'transaction_category_id' => TransactionCategory::where('code', 'wholesale')->value('id'),
            'settlement_receivable_category_id' => SettlementReceivableCategory::where('code', 'internal_balance')->value('id'),
            'billing_cycle_id' => BillingCycle::where('code', 'monthly_end_next_month_end')->value('id'),
            'invoice_required' => true,
        ]);
    }

    private function createConfirmedShipment(Customer $customer, string $date, string $price): void
    {
        $unit = Unit::where('code', 'bottle')->firstOrFail();
        $product = Product::firstOrCreate(
            ['product_code' => 'INTERNAL-TEST-SAKE'],
            ['product_type' => 'sake', 'name' => '社内請求テスト酒', 'display_name' => '社内請求テスト酒', 'base_unit_id' => $unit->id, 'sales_unit_id' => $unit->id, 'inventory_unit_id' => $unit->id, 'is_alcohol' => true],
        );
        $shipment = ShipmentHeader::create([
            'document_number' => 'INTERNAL-SHIP-'.str_replace('-', '', $date), 'status' => 'confirmed',
            'customer_id' => $customer->id, 'transaction_category_id' => $customer->transaction_category_id,
            'settlement_receivable_category_id' => $customer->settlement_receivable_category_id,
            'billing_cycle_id' => $customer->billing_cycle_id, 'document_date' => $date, 'billing_target_date' => $date,
            'confirmed_settlement_receivable_category_id' => $customer->settlement_receivable_category_id,
            'confirmed_settlement_receivable_category_code' => 'internal_balance', 'confirmed_receivable_method' => 'internal_balance',
            'confirmed_invoice_required' => true,
        ]);
        ShipmentLine::create([
            'shipment_header_id' => $shipment->id, 'line_no' => 1, 'product_id' => $product->id, 'quantity' => '1.0000', 'unit_id' => $unit->id,
            'confirmed_product_code' => $product->product_code, 'confirmed_product_name' => $product->name,
            'confirmed_display_name' => $product->display_name, 'confirmed_product_type' => $product->product_type,
            'confirmed_unit_code' => $unit->code, 'confirmed_unit_name' => $unit->name,
            'confirmed_quantity' => '1.0000', 'confirmed_unit_price' => $price, 'confirmed_at' => now(),
        ]);
    }
}
