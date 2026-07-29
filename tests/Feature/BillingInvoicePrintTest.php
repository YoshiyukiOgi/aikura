<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\InvoiceHeader;
use App\Models\Product;
use App\Models\Role;
use App\Models\SettlementReceivableCategory;
use App\Models\ShipmentHeader;
use App\Models\ShipmentLine;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Models\User;
use App\Services\Billing\ConfirmInvoiceService;
use App\Services\Billing\CreateInvoiceDraftData;
use App\Services\Billing\CreateInvoiceDraftService;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\FoundationPermissionSeeder;
use Database\Seeders\PriceMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\ShipmentMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingInvoicePrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_print_page_groups_by_tax_rate_and_uses_compact_labels(): void
    {
        $this->seed([
            FoundationPermissionSeeder::class,
            CustomerMasterSeeder::class,
            ProductUnitMasterSeeder::class,
            PriceMasterSeeder::class,
            ShipmentMasterSeeder::class,
        ]);

        $user = User::create([
            'name' => 'Billing Admin',
            'email' => 'billing-print@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail());
        $this->actingAs($user);

        $invoice = $this->prepareConfirmedInvoice();
        AppSetting::setInvoiceBankAccounts([
            ['text' => "高知銀行 安芸支店\n普通 0011837", 'is_visible' => true],
            ['text' => '非表示銀行 普通 9999999', 'is_visible' => false],
        ]);

        $response = $this->get(route('billing.invoices.print', $invoice));

        $response
            ->assertOk()
            ->assertSee('消費税率 10%')
            ->assertSee('消費税率 8%')
            ->assertSee('10% 税合計・商品合計・税込合計')
            ->assertSee('8% 税合計・商品合計・税込合計')
            ->assertSee('商品名')
            ->assertSee('容量')
            ->assertSee('備考')
            ->assertSee('前回ご請求額')
            ->assertSee('ご入金額')
            ->assertSee('繰越金額')
            ->assertSee('当月お買上額')
            ->assertSee('当月税込お買上額')
            ->assertSee('今回ご請求額')
            ->assertSee('Print Sake')
            ->assertSee('720ml')
            ->assertSee('出荷備考 1')
            ->assertSee('出荷備考 2')
            ->assertSee('お振込先')
            ->assertSee('有限会社 有光酒造場')
            ->assertSee('class="issuer-name"', false)
            ->assertSee('〒784-0033')
            ->assertSee('安芸市赤野甲38番地1')
            ->assertSee('TEL 0887-33-2117')
            ->assertSee('FAX 0887-33-4477')
            ->assertSee('登録番号 T2-4900-0201-2728')
            ->assertSee('高知銀行 安芸支店')
            ->assertSee('普通 0011837')
            ->assertSee('¥150')
            ->assertSee('¥1,500')
            ->assertSee('¥1,650')
            ->assertSee('¥240')
            ->assertSee('¥3,000')
            ->assertSee('¥3,240')
            ->assertDontSee('非表示銀行')
            ->assertDontSee('<th class="tax', false)
            ->assertDontSee('税込み金額');
    }

    public function test_invoice_print_batch_uses_the_same_compact_labels(): void
    {
        $this->seed([
            FoundationPermissionSeeder::class,
            CustomerMasterSeeder::class,
            ProductUnitMasterSeeder::class,
            PriceMasterSeeder::class,
            ShipmentMasterSeeder::class,
        ]);

        $user = User::create([
            'name' => 'Billing Admin',
            'email' => 'billing-print-batch@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail());
        $this->actingAs($user);

        $firstInvoice = $this->prepareConfirmedInvoice('2026-07-10', 'BATCH-1');
        $secondInvoice = $this->prepareConfirmedInvoice('2026-07-20', 'BATCH-2');
        AppSetting::setInvoiceBankAccounts([
            ['text' => 'PayPay銀行 ビジネス営業部 普通 2975811', 'is_visible' => true],
            ['text' => '一括印刷に出さない振込先', 'is_visible' => false],
        ]);

        $response = $this->get(route('billing.invoices.print-batch', ['ids' => implode(',', [$firstInvoice->id, $secondInvoice->id])]));

        $response
            ->assertOk()
            ->assertSee('消費税率 10%')
            ->assertSee('消費税率 8%')
            ->assertSee('10% 税合計・商品合計・税込合計')
            ->assertSee('8% 税合計・商品合計・税込合計')
            ->assertSee('前回ご請求額')
            ->assertSee('今回ご請求額')
            ->assertSee('有限会社 有光酒造場')
            ->assertSee('class="issuer-name"', false)
            ->assertSee('登録番号 T2-4900-0201-2728')
            ->assertSee('PayPay銀行 ビジネス営業部 普通 2975811')
            ->assertDontSee('一括印刷に出さない振込先')
            ->assertSee('¥150')
            ->assertSee('¥1,500')
            ->assertSee('¥1,650')
            ->assertSee('¥240')
            ->assertSee('¥3,000')
            ->assertSee('¥3,240')
            ->assertDontSee('<th class="tax', false)
            ->assertDontSee('税込み金額');
    }

    public function test_settings_can_store_ten_invoice_bank_accounts_and_visibility(): void
    {
        $this->seed(FoundationPermissionSeeder::class);

        $user = User::create([
            'name' => 'Settings Admin',
            'email' => 'settings-bank-accounts@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail());
        $this->actingAs($user);

        $accounts = collect(range(1, 10))
            ->map(fn (int $number): array => [
                'text' => "振込先 {$number}",
                'is_visible' => $number === 1 || $number === 10 ? '1' : '0',
            ])
            ->all();

        $response = $this->put(route('settings.update'), [
            'company_name' => '有限会社 有光酒造場',
            'company_postal_code' => '784-0033',
            'company_address' => '安芸市赤野甲38番地1',
            'company_phone' => '0887-33-2117',
            'company_fax' => '0887-33-4477',
            'company_registration_number' => 'T2-4900-0201-2728',
            'system_name' => '販売管理システム',
            'theme' => 'blue',
            'auto_refresh_interval_seconds' => 30,
            'alcohol_tolerance_lower' => 0.9,
            'alcohol_tolerance_upper' => 0.9,
            'invoice_bank_accounts' => $accounts,
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.index'));

        $stored = AppSetting::invoiceBankAccounts();

        $this->assertCount(10, $stored);
        $this->assertSame('振込先 1', $stored[0]['text']);
        $this->assertTrue($stored[0]['is_visible']);
        $this->assertFalse($stored[1]['is_visible']);
        $this->assertTrue($stored[9]['is_visible']);
        $this->assertSame('784-0033', AppSetting::values(['company_postal_code' => null])['company_postal_code']);
        $this->assertSame('安芸市赤野甲38番地1', AppSetting::values(['company_address' => null])['company_address']);
        $this->assertSame('T2-4900-0201-2728', AppSetting::values(['company_registration_number' => null])['company_registration_number']);
        $this->assertSame(
            ['振込先 1', '振込先 10'],
            collect(AppSetting::visibleInvoiceBankAccounts())->pluck('text')->all(),
        );

        $this->get(route('settings.index'))
            ->assertOk()
            ->assertSee('会社基礎情報')
            ->assertSee('基本表示')
            ->assertSee('在庫')
            ->assertSee('酒類ロット')
            ->assertSee('請求書')
            ->assertSee('data-tab="billing"', false)
            ->assertSee('振込先 10');
    }

    private function prepareConfirmedInvoice(string $invoiceDate = '2026-07-10', string $suffix = 'A'): InvoiceHeader
    {
        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();
        $bottle = Unit::where('code', 'bottle')->firstOrFail();
        $milliliter = Unit::where('code', 'milliliter')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'BILLING-PRINT-CUST-'.$suffix,
            'name' => '請求印刷テスト顧客 '.$suffix,
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'BILLING-PRINT-SAKE-'.$suffix,
            'product_type' => 'sake',
            'name' => 'Print Sake',
            'display_name' => 'Print Sake 720ml',
            'base_unit_id' => $bottle->id,
            'sales_unit_id' => $bottle->id,
            'inventory_unit_id' => $bottle->id,
            'capacity_value' => '720.0000',
            'capacity_unit_id' => $milliliter->id,
            'is_alcohol' => true,
        ]);

        $shipment = ShipmentHeader::create([
            'document_number' => 'SHIP-PRINT-'.$suffix,
            'status' => 'confirmed',
            'customer_id' => $customer->id,
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
            'document_date' => $invoiceDate,
            'billing_target_date' => $invoiceDate,
            'note' => '出荷ヘッダー備考',
        ]);

        ShipmentLine::create([
            'shipment_header_id' => $shipment->id,
            'line_no' => 1,
            'product_id' => $product->id,
            'quantity' => '1.0000',
            'unit_id' => $bottle->id,
            'note' => '出荷備考 1',
            'confirmed_product_code' => $product->product_code,
            'confirmed_product_name' => $product->name,
            'confirmed_display_name' => $product->display_name,
            'confirmed_product_type' => $product->product_type,
            'confirmed_unit_code' => $bottle->code,
            'confirmed_unit_name' => $bottle->name,
            'confirmed_quantity' => '1.0000',
            'confirmed_unit_price' => '1500.0000',
            'confirmed_capacity_value' => '720.0000',
            'confirmed_capacity_unit_id' => $milliliter->id,
            'confirmed_consumption_tax_rate' => '0.1000',
            'confirmed_at' => now(),
        ]);

        ShipmentLine::create([
            'shipment_header_id' => $shipment->id,
            'line_no' => 2,
            'product_id' => $product->id,
            'quantity' => '2.0000',
            'unit_id' => $bottle->id,
            'note' => '出荷備考 2',
            'confirmed_product_code' => $product->product_code,
            'confirmed_product_name' => $product->name,
            'confirmed_display_name' => $product->display_name,
            'confirmed_product_type' => $product->product_type,
            'confirmed_unit_code' => $bottle->code,
            'confirmed_unit_name' => $bottle->name,
            'confirmed_quantity' => '2.0000',
            'confirmed_unit_price' => '1500.0000',
            'confirmed_capacity_value' => '720.0000',
            'confirmed_capacity_unit_id' => $milliliter->id,
            'confirmed_consumption_tax_rate' => '0.0800',
            'confirmed_at' => now(),
        ]);

        $invoice = app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: $invoiceDate,
            shipmentHeaderIds: [$shipment->id],
        ));

        return app(ConfirmInvoiceService::class)->confirm($invoice);
    }
}
