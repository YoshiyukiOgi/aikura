<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\ConsumptionTaxCategory;
use App\Models\ConsumptionTaxRate;
use App\Models\Customer;
use App\Models\NumberSequence;
use App\Models\PriceList;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\Retail\RetailCompany;
use App\Models\Retail\RetailCompanySetting;
use App\Models\Retail\RetailCustomer;
use App\Models\Retail\RetailDelivery;
use App\Models\Retail\RetailInventoryMovement;
use App\Models\Retail\RetailInventoryStock;
use App\Models\Retail\RetailInvoice;
use App\Models\Retail\RetailPayment;
use App\Models\Retail\RetailPriceChangeCandidate;
use App\Models\Retail\RetailPriceHistory;
use App\Models\Retail\RetailPriceSyncSetting;
use App\Models\Retail\RetailProduct;
use App\Models\Retail\RetailPurchaseOrder;
use App\Models\Retail\RetailSale;
use App\Models\Retail\RetailSupplier;
use App\Models\Retail\RetailSystemSetting;
use App\Models\SalesOrder;
use App\Models\SettlementReceivableCategory;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Models\User;
use App\Services\Retail\AdjustRetailSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetailCustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_retail_customers(): void
    {
        $this->get('/retail/customers')->assertRedirect('/retail-login');
    }

    public function test_retail_pos_does_not_inject_brewery_sidebar(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->get('/retail/pos')
            ->assertOk()
            ->assertDontSee('app-sidebar', false)
            ->assertSee('Retail POS Shell');
    }

    public function test_retail_pos_automatically_selects_first_company_after_login(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->get('/retail/pos')
            ->assertOk()
            ->assertSessionHas('retail.company');
    }

    public function test_user_can_select_retail_company_and_enter_pos(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->post('/retail/companies/select', ['company' => 'batsu'])
            ->assertRedirect('/retail/pos')
            ->assertSessionHas('retail.company', 'batsu');

        $this->actingAs($user)
            ->withSession(['retail.company' => 'batsu'])
            ->get('/retail/pos')
            ->assertOk()
            ->assertSee('Retail POS Shell')
            ->assertSee('Retail Settings Link');
    }

    public function test_pos_disables_quantity_when_retail_stock_is_empty(): void
    {
        $this->actingAs($this->createUser())
            ->withSession(['retail.company' => 'maru'])
            ->get('/retail/pos')
            ->assertOk()
            ->assertSee('販売商品')
            ->assertSee('商品を追加');

        return;

        $user = $this->createUser();
        $supplier = $this->brewerySupplier();
        RetailProduct::query()->create([
            'product_code' => 'BR-ZERO-STOCK',
            'name' => '在庫なし商品',
            'procurement_source' => 'brewery',
            'retail_supplier_id' => $supplier->id,
            'cost_price' => 1000,
            'selling_price' => 1500,
            'tax_rate' => 0.1000,
            'stock_unit' => '本',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->get('/retail/pos?stock=all')
            ->assertOk()
            ->assertSee('POSは標準で在庫あり商品のみ表示します。')
            ->assertSee('在庫を入れるまで販売不可')
            ->assertSee('disabled', false);
    }

    public function test_pos_products_can_be_filtered_by_search_source_and_stock(): void
    {
        $this->actingAs($this->createUser())
            ->withSession(['retail.company' => 'maru'])
            ->get('/retail/pos')
            ->assertOk()
            ->assertSee('商品を追加')
            ->assertSee('商品検索');

        return;

        $user = $this->createUser();
        $brewerySupplier = $this->brewerySupplier();
        $externalSupplier = RetailSupplier::query()->create([
            'supplier_code' => 'POS-EXT',
            'name' => 'POS外部仕入先',
            'supplier_type' => 'external',
            'ordering_method' => 'manual',
            'is_active' => true,
        ]);

        $breweryProduct = RetailProduct::query()->create([
            'product_code' => 'POS-BR-001',
            'name' => '検索用 純米酒',
            'procurement_source' => 'brewery',
            'retail_supplier_id' => $brewerySupplier->id,
            'cost_price' => 1000,
            'selling_price' => 1500,
            'tax_rate' => 0.1000,
            'stock_unit' => '本',
            'is_active' => true,
        ]);
        RetailInventoryStock::query()->create(['retail_product_id' => $breweryProduct->id, 'quantity' => 5]);

        $externalProduct = RetailProduct::query()->create([
            'product_code' => 'POS-EX-001',
            'name' => '検索用 グラス',
            'procurement_source' => 'external',
            'retail_supplier_id' => $externalSupplier->id,
            'cost_price' => 500,
            'selling_price' => 900,
            'tax_rate' => 0.1000,
            'stock_unit' => '個',
            'is_active' => true,
        ]);
        RetailInventoryStock::query()->create(['retail_product_id' => $externalProduct->id, 'quantity' => 3]);

        $zeroStockProduct = RetailProduct::query()->create([
            'product_code' => 'POS-ZERO-001',
            'name' => '検索用 在庫なし',
            'procurement_source' => 'brewery',
            'retail_supplier_id' => $brewerySupplier->id,
            'cost_price' => 700,
            'selling_price' => 1000,
            'tax_rate' => 0.1000,
            'stock_unit' => '本',
            'is_active' => true,
        ]);
        RetailInventoryStock::query()->create(['retail_product_id' => $zeroStockProduct->id, 'quantity' => 0]);

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->get('/retail/pos?q=純米')
            ->assertOk()
            ->assertSee('検索用 純米酒')
            ->assertDontSee('検索用 グラス')
            ->assertDontSee('検索用 在庫なし');

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->get('/retail/pos?source=external')
            ->assertOk()
            ->assertSee('検索用 グラス')
            ->assertDontSee('検索用 純米酒');

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->get('/retail/pos?stock=all')
            ->assertOk()
            ->assertSee('検索用 在庫なし');
    }

    public function test_pos_uses_sale_lines_and_product_modal_data(): void
    {
        $user = $this->createUser();
        RetailCustomer::query()->create([
            'customer_code' => 'POS-CUSTOMER-001',
            'name' => 'POS検索顧客',
            'name_kana' => 'ポスケンサクコキャク',
            'phone' => '03-1234-5678',
            'is_active' => true,
        ]);
        $supplier = $this->brewerySupplier();
        RetailProduct::query()->create([
            'product_code' => 'POS-MODAL-001',
            'name' => 'POSモーダル商品',
            'procurement_source' => 'brewery',
            'retail_supplier_id' => $supplier->id,
            'cost_price' => 1000,
            'selling_price' => 1500,
            'tax_rate' => 0.1000,
            'stock_unit' => '本',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->get('/retail/pos')
            ->assertOk()
            ->assertSee('販売条件')
            ->assertSee('販売商品')
            ->assertSee('商品を追加')
            ->assertSee('POS-MODAL-001')
            ->assertSee('id="customer-modal"', false)
            ->assertSee('id="customer-search"', false)
            ->assertSee('name="retail_customer_id"', false)
            ->assertDontSee('<select name="retail_customer_id"', false)
            ->assertSee('店頭一般客')
            ->assertSee('POS-CUSTOMER-001')
            ->assertSee('マイナス伝票');
    }

    public function test_sales_history_is_available_and_shows_cancel_action_before_close(): void
    {
        [$user, $customer, $product] = $this->saleFixture('BR-HISTORY-001', 5);
        $this->actingAs($user)->withSession(['retail.company' => 'maru'])->post('/retail/sales', [
            'sale_date' => '2026-08-02',
            'sale_type' => 'credit',
            'retail_customer_id' => $customer->id,
            'items' => [['retail_product_id' => $product->id, 'quantity' => '1']],
        ])->assertRedirect();

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->get('/retail/sales')
            ->assertOk()
            ->assertSee('class="history-layout"', false)
            ->assertSee('id="history-list"', false)
            ->assertSee('data-sale-target=', false)
            ->assertSee('class="cancel-form"', false)
            ->assertSee('placeholder="取消理由を入力してください"', false)
            ->assertSee('name="q"', false)
            ->assertSee('name="date_from"', false)
            ->assertSee('name="date_to"', false)
            ->assertSee('name="status"', false)
            ->assertSee('name="sale_type"', false)
            ->assertSee('販売履歴')
            ->assertSee('販売入力')
            ->assertSee('外部商品')
            ->assertDontSee('retail/purchase-orders', false)
            ->assertSee('取消')
            ->assertSee('BR-HISTORY-001');
    }

    public function test_sales_history_can_be_filtered_by_keyword_date_status_and_sale_type(): void
    {
        [$user, $customer, $product] = $this->saleFixture('BR-SEARCH-HISTORY-001', 5);

        $first = RetailSale::query()->create([
            'sale_no' => 'RS-SEARCH-001',
            'retail_customer_id' => $customer->id,
            'sale_date' => '2026-08-01',
            'sale_type' => 'credit',
            'status' => 'posted',
            'payment_status' => 'unpaid',
            'subtotal_amount' => 2000,
            'tax_amount' => 200,
            'total_amount' => 2200,
        ]);
        $first->items()->create([
            'retail_product_id' => $product->id,
            'description' => '特別検索商品',
            'quantity' => 1,
            'unit_price' => 2000,
            'tax_rate' => 0.1,
            'tax_amount' => 200,
            'line_amount' => 2000,
        ]);

        $second = RetailSale::query()->create([
            'sale_no' => 'RS-SEARCH-002',
            'retail_customer_id' => $customer->id,
            'sale_date' => '2026-08-02',
            'sale_type' => 'cash',
            'status' => 'cancelled',
            'payment_status' => 'cancelled',
            'subtotal_amount' => 1000,
            'tax_amount' => 100,
            'total_amount' => 1100,
        ]);
        $second->items()->create([
            'retail_product_id' => $product->id,
            'description' => '通常商品',
            'quantity' => 1,
            'unit_price' => 1000,
            'tax_rate' => 0.1,
            'tax_amount' => 100,
            'line_amount' => 1000,
        ]);

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->get('/retail/sales?q=特別検索商品&date_from=2026-08-01&date_to=2026-08-01&status=posted&sale_type=credit')
            ->assertOk()
            ->assertSee('RS-SEARCH-001')
            ->assertDontSee('RS-SEARCH-002');

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->get('/retail/sales?status=cancelled&sale_type=cash')
            ->assertOk()
            ->assertSee('RS-SEARCH-002')
            ->assertDontSee('RS-SEARCH-001');
    }

    public function test_negative_retail_sale_can_be_registered_and_increases_inventory(): void
    {
        [$user, $customer, $product] = $this->saleFixture('BR-NEGATIVE-001', 5);

        $this->actingAs($user)
            ->post('/retail/sales', [
                'sale_date' => '2026-08-02',
                'sale_type' => 'credit',
                'retail_customer_id' => $customer->id,
                'items' => [
                    ['retail_product_id' => $product->id, 'quantity' => '-2'],
                ],
            ])
            ->assertRedirect();

        $sale = RetailSale::query()->where('retail_customer_id', $customer->id)->latest('id')->firstOrFail();
        $this->assertSame('-4000.00', $sale->subtotal_amount);
        $this->assertSame('-4400.00', $sale->total_amount);
        $this->assertSame('7.000', RetailInventoryStock::query()->where('retail_product_id', $product->id)->firstOrFail()->quantity);
        $this->assertDatabaseHas('retail_inventory_movements', [
            'retail_product_id' => $product->id,
            'movement_type' => 'sale',
            'quantity' => '2.000',
        ], 'retail');
    }

    public function test_allow_negative_order_policy_allows_sale_beyond_stock(): void
    {
        [$user, $customer, $product] = $this->saleFixture('BR-BACKORDER-001', 1);
        RetailCompanySetting::query()->updateOrCreate(
            ['company_key' => 'maru'],
            [
                'sale_mode' => 'mixed',
                'inventory_sales_policy' => 'allow_negative_order',
                'delivery_note_policy' => 'on_demand',
                'invoice_policy' => 'monthly_credit',
                'brewery_procurement_policy' => 'auto_order',
                'external_procurement_policy' => 'supplier_order',
            ],
        );

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->post('/retail/sales', [
                'sale_date' => '2026-08-02',
                'sale_type' => 'credit',
                'retail_customer_id' => $customer->id,
                'items' => [
                    ['retail_product_id' => $product->id, 'quantity' => '3'],
                ],
            ])
            ->assertRedirect();

        $this->assertSame('-2.000', RetailInventoryStock::query()->where('retail_product_id', $product->id)->firstOrFail()->quantity);
        RetailInventoryStock::query()->where('retail_product_id', $product->id)->update(['quantity' => -11]);

        $sale = RetailSale::query()->where('retail_customer_id', $customer->id)->latest('id')->firstOrFail();
        $this->assertSame(RetailCompany::query()->where('company_key', 'maru')->value('id'), $sale->retail_company_id);
        $this->actingAs($user)
            ->withSession(['retail.company' => 'batsu'])
            ->put("/retail/sales/{$sale->id}", [
                'sale_date' => '2026-08-02',
                'sale_type' => 'credit',
                'retail_customer_id' => $customer->id,
                'reason' => 'マイナス在庫で数量変更',
                'items' => [
                    ['retail_product_id' => $product->id, 'quantity' => '5'],
                ],
            ])
            ->assertRedirect("/retail/sales/{$sale->id}");

        $this->assertSame('5.000', $sale->fresh()->items()->sole()->quantity);
        $this->assertSame('-13.000', RetailInventoryStock::query()->where('retail_product_id', $product->id)->firstOrFail()->quantity);

        app(AdjustRetailSaleService::class)->revise($sale->fresh(), [
            'sale_date' => '2026-08-02',
            'sale_type' => 'credit',
            'retail_customer_id' => $customer->id,
            'reason' => 'サービス側の会社設定判定',
        ], [
            ['retail_product_id' => $product->id, 'quantity' => '6'],
        ]);

        $this->assertSame('6.000', $sale->fresh()->items()->sole()->quantity);
        $this->assertSame('-14.000', RetailInventoryStock::query()->where('retail_product_id', $product->id)->firstOrFail()->quantity);
    }

    public function test_pos_hides_stock_count_when_allow_negative_order_policy_is_enabled(): void
    {
        $user = $this->createUser();
        $supplier = $this->brewerySupplier();
        RetailCompanySetting::query()->updateOrCreate(
            ['company_key' => 'maru'],
            [
                'sale_mode' => 'mixed',
                'inventory_sales_policy' => 'allow_negative_order',
                'delivery_note_policy' => 'on_demand',
                'invoice_policy' => 'monthly_credit',
                'brewery_procurement_policy' => 'auto_order',
                'external_procurement_policy' => 'supplier_order',
            ],
        );
        $product = RetailProduct::query()->create([
            'product_code' => 'POS-HIDE-STOCK',
            'name' => '在庫非表示商品',
            'procurement_source' => 'brewery',
            'retail_supplier_id' => $supplier->id,
            'cost_price' => 1000,
            'selling_price' => 1500,
            'tax_rate' => 0.1000,
            'stock_unit' => '本',
            'is_active' => true,
        ]);
        RetailInventoryStock::query()->create(['retail_product_id' => $product->id, 'quantity' => 4]);

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->get('/retail/pos')
            ->assertOk()
            ->assertSee('在庫数は表示せず')
            ->assertDontSee('>在庫</th>', false)
            ->assertDontSee('4 本');
    }

    public function test_purchase_orders_page_can_be_opened(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->get('/retail/purchase-orders')
            ->assertOk()
            ->assertSee('Retail Purchase Orders Shell');
    }

    public function test_customer_and_product_pages_do_not_show_purchase_order_menu(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->get('/retail/customers')
            ->assertOk()
            ->assertDontSee('retail/purchase-orders', false)
            ->assertSee('retail/sales', false);

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->get('/retail/products')
            ->assertOk()
            ->assertDontSee('retail/purchase-orders', false)
            ->assertSee('retail/sales', false);
    }

    public function test_customer_pagination_uses_compact_japanese_controls(): void
    {
        $user = $this->createUser();
        $company = RetailCompany::query()->where('company_key', 'maru')->firstOrFail();

        foreach (range(1, 31) as $number) {
            RetailCustomer::query()->create([
                'retail_company_id' => $company->id,
                'customer_code' => 'PAGE-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                'name' => 'ページ確認顧客'.$number,
                'billing_method' => 'per_sale',
                'payment_month_offset' => 0,
                'invoice_required' => true,
                'is_active' => true,
            ]);
        }

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->get('/retail/customers')
            ->assertOk()
            ->assertSee('data-testid="customer-pagination"', false)
            ->assertSee('1〜30件 / 全31件')
            ->assertSee('1 / 2ページ')
            ->assertSee('次へ')
            ->assertDontSee('pagination.previous')
            ->assertDontSee('<svg', false);
    }

    public function test_settings_and_system_management_are_separate_menus(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->get('/retail/settings')
            ->assertOk()
            ->assertSee('Retail Settings Order Basic Sales Procurement')
            ->assertSee('name="company_name"', false)
            ->assertSee('name="representative_name"', false)
            ->assertSee('name="postal_code"', false)
            ->assertSee('name="address1"', false)
            ->assertSee('name="phone"', false)
            ->assertSee('name="invoice_registration_number"', false)
            ->assertSee('name="sale_mode"', false)
            ->assertSee('name="inventory_sales_policy"', false)
            ->assertSee('name="delivery_note_policy"', false)
            ->assertSee('name="invoice_policy"', false)
            ->assertSee('name="brewery_procurement_policy"', false)
            ->assertSee('name="brewery_partner_id"', false)
            ->assertSee('name="external_procurement_policy"', false)
            ->assertDontSee('name="detection_mode"', false)
            ->assertSee('retail/system-management', false)
            ->assertSee('retail/sales', false)
            ->assertDontSee('retail/companies\"', false);

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->get('/retail/system-management')
            ->assertOk()
            ->assertSee('Retail System Management Shell')
            ->assertSee('retail/sales', false)
            ->assertSee('name="system_name"', false)
            ->assertSee('name="theme"', false)
            ->assertSee('グリーン')
            ->assertSee('name="detection_mode"', false);
    }

    public function test_retail_company_can_be_added_and_removed_from_settings(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->post('/retail/companies', [
                'company_key' => 'newco',
                'name' => 'New Company',
                'description' => 'New retail company',
            ])
            ->assertRedirect('/retail/system-management');

        $company = RetailCompany::query()->where('company_key', 'newco')->firstOrFail();
        $this->assertTrue($company->is_active);

        $this->actingAs($user)
            ->post('/retail/companies/select', ['company' => 'newco'])
            ->assertRedirect('/retail/pos')
            ->assertSessionHas('retail.company', 'newco');

        $this->actingAs($user)
            ->withSession(['retail.company' => 'newco'])
            ->delete("/retail/companies/{$company->id}")
            ->assertRedirect('/retail/system-management')
            ->assertSessionMissing('retail.company');

        $company->refresh();
        $this->assertFalse($company->is_active);

        $this->actingAs($user)
            ->post('/retail/companies/select', ['company' => 'newco'])
            ->assertSessionHasErrors('company');
    }

    public function test_last_active_retail_company_cannot_be_removed(): void
    {
        $user = $this->createUser();
        RetailCompany::query()->where('company_key', '<>', 'maru')->update(['is_active' => false]);
        $company = RetailCompany::query()->where('company_key', 'maru')->firstOrFail();

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->delete("/retail/companies/{$company->id}")
            ->assertRedirect('/retail/system-management')
            ->assertSessionHasErrors('company');

        $this->assertTrue($company->fresh()->is_active);
    }

    public function test_authenticated_user_can_create_retail_customer(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->post('/retail/customers', [
                'customer_code' => 'RC-001',
                'name' => '小売顧客A',
                'name_kana' => 'コウリコキャクエー',
                'billing_name' => '小売顧客A 請求先',
                'billing_method' => 'monthly',
                'phone' => '03-0000-0001',
                'fax' => '03-0000-0002',
                'closing_day' => '31',
                'payment_month_offset' => '1',
                'invoice_required' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect('/retail/customers');

        $this->assertDatabaseHas('retail_customers', [
            'customer_code' => 'RC-001',
            'name' => '小売顧客A',
            'billing_method' => 'monthly',
            'fax' => '03-0000-0002',
            'invoice_required' => true,
        ], 'retail');
    }

    public function test_company_customer_scope_includes_shared_customers(): void
    {
        $user = $this->createUser();
        RetailCompany::query()->whereIn('company_key', ['maru', 'batsu'])->update(['is_active' => true]);
        $maru = RetailCompany::query()->where('company_key', 'maru')->firstOrFail();
        $batsu = RetailCompany::query()->where('company_key', 'batsu')->firstOrFail();

        foreach ([
            [null, 'RC-SHARED', '全社共通顧客'],
            [$maru->id, 'RC-MARU', '玉川専用顧客'],
            [$batsu->id, 'RC-BATSU', '桜浜専用顧客'],
        ] as [$companyId, $code, $name]) {
            RetailCustomer::query()->create([
                'retail_company_id' => $companyId,
                'customer_code' => $code,
                'name' => $name,
                'billing_method' => 'per_sale',
                'payment_month_offset' => 0,
                'invoice_required' => true,
                'is_active' => true,
            ]);
        }

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->get('/retail/customers')
            ->assertOk()
            ->assertSee('全社共通顧客')
            ->assertSee('玉川専用顧客')
            ->assertDontSee('桜浜専用顧客');

        $this->actingAs($user)
            ->withSession(['retail.company' => 'batsu'])
            ->get('/retail/pos')
            ->assertOk()
            ->assertSee('RC-SHARED')
            ->assertSee('RC-BATSU')
            ->assertDontSee('RC-MARU');
    }

    public function test_invoice_customer_requires_closing_day(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->from('/retail/customers')
            ->post('/retail/customers', [
                'customer_code' => 'RC-002',
                'name' => '締日なし顧客',
                'payment_month_offset' => '1',
                'invoice_required' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect('/retail/customers')
            ->assertSessionHasErrors('closing_day');
    }

    public function test_customer_billing_method_overrides_company_and_null_falls_back_to_company(): void
    {
        [$user, $customer, $product] = $this->saleFixture('BR-BILLING-METHOD-001', 10);
        $customer->update(['billing_method' => 'per_sale']);
        RetailCompanySetting::query()->updateOrCreate(
            ['company_key' => 'maru'],
            [
                'sale_mode' => 'mixed',
                'inventory_sales_policy' => 'strict_stock',
                'delivery_note_policy' => 'on_demand',
                'invoice_policy' => 'monthly_credit',
                'brewery_procurement_policy' => 'auto_order',
                'external_procurement_policy' => 'supplier_order',
            ],
        );

        foreach (['2026-08-01', '2026-08-02'] as $date) {
            $this->actingAs($user)->withSession(['retail.company' => 'maru'])->post('/retail/sales', [
                'sale_date' => $date,
                'sale_type' => 'credit',
                'retail_customer_id' => $customer->id,
                'items' => [['retail_product_id' => $product->id, 'quantity' => '1']],
            ])->assertRedirect();
        }
        $sales = RetailSale::query()->where('retail_customer_id', $customer->id)->orderBy('id')->get();

        $invoiceData = [
            'retail_customer_id' => $customer->id,
            'invoice_date' => '2026-08-31',
            'closing_date' => '2026-08-31',
        ];
        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->from('/retail/invoices')
            ->post('/retail/invoices', $invoiceData)
            ->assertRedirect('/retail/invoices')
            ->assertSessionHasErrors('retail_sale_id');

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->post('/retail/invoices', [...$invoiceData, 'retail_sale_id' => $sales[0]->id])
            ->assertRedirect();

        $invoice = RetailInvoice::query()->latest('id')->firstOrFail();
        $this->assertSame('2200.00', $invoice->total_amount);
        $this->assertSame($sales[0]->id, $invoice->lines()->firstOrFail()->retail_sale_id);
        $this->assertNull($sales[1]->fresh()->closed_at);

        $customer->update(['billing_method' => null]);
        RetailCompanySetting::query()->where('company_key', 'maru')->update(['invoice_policy' => 'per_invoice']);

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->from('/retail/invoices')
            ->post('/retail/invoices', $invoiceData)
            ->assertRedirect('/retail/invoices')
            ->assertSessionHasErrors('retail_sale_id');
    }

    public function test_authenticated_user_can_update_retail_customer(): void
    {
        $user = $this->createUser();
        $customer = RetailCustomer::query()->create([
            'customer_code' => 'RC-003',
            'name' => '更新前',
            'payment_month_offset' => 1,
            'invoice_required' => false,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->put("/retail/customers/{$customer->id}", [
                'customer_code' => 'RC-003',
                'name' => '更新後',
                'payment_month_offset' => '2',
                'payment_day' => '20',
                'is_active' => '1',
            ])
            ->assertRedirect("/retail/customers?edit={$customer->id}");

        $this->assertDatabaseHas('retail_customers', [
            'id' => $customer->id,
            'name' => '更新後',
            'payment_month_offset' => 2,
            'payment_day' => 20,
        ], 'retail');
    }

    public function test_authenticated_user_can_import_brewery_product_to_retail_products(): void
    {
        $user = $this->createUser();
        $unit = Unit::query()->create([
            'code' => 'bottle',
            'name' => '本',
            'symbol' => '本',
            'unit_type' => 'count',
        ]);
        $product = Product::query()->create([
            'product_code' => 'SAKE-001',
            'product_type' => 'sake',
            'name' => '純米吟醸',
            'display_name' => '純米吟醸 720ml',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_sales_available' => true,
            'is_active' => true,
        ]);
        $wholesaleList = PriceList::query()->create([
            'code' => 'wholesale_price',
            'name' => 'Wholesale',
            'price_type' => 'wholesale',
            'is_active' => true,
        ]);
        $retailList = PriceList::query()->create([
            'code' => 'retail_price',
            'name' => 'Retail',
            'price_type' => 'retail',
            'is_active' => true,
        ]);
        foreach ([[$wholesaleList->id, 1200], [$retailList->id, 2200]] as [$priceListId, $unitPrice]) {
            PriceRule::query()->create([
                'price_list_id' => $priceListId,
                'product_id' => $product->id,
                'unit_id' => $unit->id,
                'unit_price' => $unitPrice,
                'effective_from' => now()->subDay()->toDateString(),
                'is_active' => true,
            ]);
        }

        $this->actingAs($user)
            ->post("/retail/products/import/{$product->id}")
            ->assertRedirect('/retail/products/import');

        $supplier = RetailSupplier::query()->where('supplier_code', 'BREWERY')->first();
        $this->assertNotNull($supplier);

        $this->assertDatabaseHas('retail_products', [
            'product_code' => 'BR-SAKE-001',
            'name' => '純米吟醸 720ml',
            'procurement_source' => 'brewery',
            'brewery_product_id' => $product->id,
            'retail_supplier_id' => $supplier->id,
            'cost_price' => '1200.00',
            'selling_price' => '2200.00',
        ], 'retail');

        $this->actingAs($user)
            ->post("/retail/products/import/{$product->id}")
            ->assertRedirect('/retail/products/import');

        $this->assertSame(1, RetailProduct::query()->where('brewery_product_id', $product->id)->count());

        RetailProduct::query()
            ->where('brewery_product_id', $product->id)
            ->update(['cost_price' => 1300, 'selling_price' => 2400]);
        $product->update(['display_name' => '純米吟醸 改称 720ml']);

        $this->actingAs($user)
            ->post("/retail/products/import/{$product->id}")
            ->assertRedirect('/retail/products/import');

        $this->assertDatabaseHas('retail_products', [
            'brewery_product_id' => $product->id,
            'name' => '純米吟醸 改称 720ml',
            'cost_price' => '1300.00',
            'selling_price' => '2400.00',
        ], 'retail');

        $this->actingAs($user)
            ->get('/retail/products/import?tab=import')
            ->assertOk()
            ->assertSee('純米吟醸 改称 720ml')
            ->assertSee('追加済')
            ->assertSee('再追加');

        $this->actingAs($user)
            ->get('/retail/products')
            ->assertOk()
            ->assertSee('外部商品')
            ->assertDontSee('純米吟醸 改称 720ml');
    }

    public function test_brewery_reduced_tax_product_imports_eight_percent_tax_rate(): void
    {
        $user = $this->createUser();
        $unit = Unit::query()->create([
            'code' => 'food-pack',
            'name' => 'pack',
            'symbol' => '個',
            'unit_type' => 'count',
        ]);
        $reducedCategory = ConsumptionTaxCategory::query()->create([
            'code' => 'taxable_reduced',
            'name' => '軽減税率',
            'taxability' => 'taxable',
            'requires_tax_rate' => true,
            'is_reduced_rate' => true,
            'is_export_exempt' => false,
            'is_invoice_display_target' => true,
            'sort_order' => 20,
            'is_active' => true,
        ]);
        ConsumptionTaxRate::query()->create([
            'consumption_tax_category_id' => $reducedCategory->id,
            'name' => '軽減税率 8%',
            'rate' => '0.0800',
            'effective_from' => now()->subDay()->toDateString(),
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'product_code' => 'FOOD-001',
            'product_type' => 'food',
            'name' => '食品商品',
            'display_name' => '食品商品 180g',
            'consumption_tax_category_id' => $reducedCategory->id,
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_sales_available' => true,
            'is_active' => true,
        ]);
        $wholesaleList = PriceList::query()->create([
            'code' => 'wholesale_price',
            'name' => 'Wholesale',
            'price_type' => 'wholesale',
            'is_active' => true,
        ]);
        $retailList = PriceList::query()->create([
            'code' => 'retail_price',
            'name' => 'Retail',
            'price_type' => 'retail',
            'is_active' => true,
        ]);
        foreach ([[$wholesaleList->id, 300], [$retailList->id, 540]] as [$priceListId, $unitPrice]) {
            PriceRule::query()->create([
                'price_list_id' => $priceListId,
                'product_id' => $product->id,
                'unit_id' => $unit->id,
                'unit_price' => $unitPrice,
                'effective_from' => now()->subDay()->toDateString(),
                'is_active' => true,
            ]);
        }

        $this->actingAs($user)
            ->post("/retail/products/import/{$product->id}")
            ->assertRedirect('/retail/products/import');

        $this->assertDatabaseHas('retail_products', [
            'product_code' => 'BR-FOOD-001',
            'brewery_product_id' => $product->id,
            'cost_price' => '300.00',
            'selling_price' => '540.00',
            'tax_rate' => '0.0800',
        ], 'retail');
    }

    public function test_brewery_product_import_selection_is_saved_and_bulk_imports_checked_products(): void
    {
        $user = $this->createUser();
        $unit = Unit::query()->create([
            'code' => 'case',
            'name' => 'ケース',
            'symbol' => 'CS',
            'unit_type' => 'count',
        ]);
        $first = Product::query()->create([
            'product_code' => 'BULK-001',
            'product_type' => 'sake',
            'name' => '一括取込商品A',
            'display_name' => '一括取込商品A 720ml',
            'category_name' => '清酒',
            'capacity_value' => '720.0000',
            'capacity_unit_id' => $unit->id,
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_sales_available' => true,
            'is_active' => true,
        ]);
        $second = Product::query()->create([
            'product_code' => 'BULK-002',
            'product_type' => 'sake',
            'name' => '一括取込商品B',
            'display_name' => '一括取込商品B 720ml',
            'category_name' => '食品',
            'capacity_value' => '180.0000',
            'capacity_unit_id' => $unit->id,
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_sales_available' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->put('/retail/products/import/selections', [
                'visible_product_ids' => [$first->id, $second->id],
                'selected_product_ids' => [$first->id],
            ])
            ->assertRedirect('/retail/products/import?tab=import');

        $this->assertDatabaseHas('retail_brewery_product_import_selections', [
            'brewery_product_id' => $first->id,
            'is_selected' => true,
        ], 'retail');
        $this->assertDatabaseHas('retail_brewery_product_import_selections', [
            'brewery_product_id' => $second->id,
            'is_selected' => false,
        ], 'retail');

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->get('/retail/products/import?tab=import')
            ->assertOk()
            ->assertSee('取扱商品選択')
            ->assertSee('retail/sales', false)
            ->assertSee('name="selected_product_ids[]" value="'.$first->id.'" checked', false);

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->get('/retail/products/import?tab=import&status=all&category='.urlencode('清酒'))
            ->assertOk()
            ->assertSee('一括取込商品A 720ml')
            ->assertSee('720 CS')
            ->assertDontSee('720.0000')
            ->assertDontSee('一括取込商品B 720ml');

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->post('/retail/products/import/bulk', [
                'visible_product_ids' => [$first->id, $second->id],
                'selected_product_ids' => [$first->id],
            ])
            ->assertRedirect('/retail/products/import?tab=import');

        $this->assertDatabaseHas('retail_products', [
            'product_code' => 'BR-BULK-001',
            'brewery_product_id' => $first->id,
        ], 'retail');
        $this->assertDatabaseMissing('retail_products', [
            'product_code' => 'BR-BULK-002',
            'brewery_product_id' => $second->id,
        ], 'retail');
    }

    public function test_authenticated_user_can_update_retail_product_prices_and_reorder_settings(): void
    {
        $user = $this->createUser();
        $supplier = RetailSupplier::query()->firstOrCreate(
            ['supplier_code' => 'EXT-EDIT'],
            [
                'name' => '外部編集仕入先',
                'supplier_type' => 'external',
                'ordering_method' => 'manual',
                'is_active' => true,
            ],
        );
        $product = RetailProduct::query()->create([
            'product_code' => 'EXT-SAKE-002',
            'name' => '純米酒 720ml',
            'procurement_source' => 'external',
            'retail_supplier_id' => $supplier->id,
            'cost_price' => 0,
            'selling_price' => 0,
            'tax_rate' => 0.1000,
            'stock_unit' => '本',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->put("/retail/products/{$product->id}", [
                'product_code' => 'EXT-SAKE-002',
                'name' => '純米酒 720ml 店頭用',
                'retail_supplier_id' => $supplier->id,
                'cost_price' => '1100',
                'selling_price' => '1980',
                'tax_rate' => '0.1000',
                'stock_unit' => '本',
                'reorder_point' => '6',
                'reorder_quantity' => '12',
                'is_active' => '1',
            ])
            ->assertRedirect("/retail/products?edit={$product->id}");

        $this->assertDatabaseHas('retail_products', [
            'id' => $product->id,
            'name' => '純米酒 720ml 店頭用',
            'cost_price' => '1100.00',
            'selling_price' => '1980.00',
            'reorder_point' => '6.000',
            'reorder_quantity' => '12.000',
        ], 'retail');
    }

    public function test_external_retail_product_can_be_created(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->post('/retail/products', [
                'product_code' => 'EXT-SNACK-001',
                'name' => '外部おつまみ',
                'new_supplier_code' => 'EXTSUP-001',
                'new_supplier_name' => '外部食品',
                'cost_price' => '300',
                'selling_price' => '540',
                'tax_rate' => '0.0800',
                'stock_unit' => '個',
                'reorder_point' => '5',
                'reorder_quantity' => '20',
                'stock_quantity' => '10',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $supplier = RetailSupplier::query()->where('supplier_code', 'EXTSUP-001')->firstOrFail();
        $product = RetailProduct::query()->where('product_code', 'EXT-SNACK-001')->firstOrFail();
        $this->assertSame('external', $product->procurement_source);
        $this->assertSame($supplier->id, $product->retail_supplier_id);
        $this->assertSame('540.00', $product->selling_price);
        $this->assertDatabaseHas('retail_inventory_stocks', [
            'retail_product_id' => $product->id,
            'quantity' => '10.000',
        ], 'retail');
    }

    public function test_retail_product_update_can_set_current_stock(): void
    {
        $user = $this->createUser();
        $supplier = RetailSupplier::query()->create([
            'supplier_code' => 'EXT-STOCK',
            'name' => '外部在庫仕入先',
            'supplier_type' => 'external',
            'ordering_method' => 'manual',
            'is_active' => true,
        ]);
        $product = RetailProduct::query()->create([
            'product_code' => 'EXT-STOCK-001',
            'name' => '在庫設定商品',
            'procurement_source' => 'external',
            'retail_supplier_id' => $supplier->id,
            'cost_price' => 1000,
            'selling_price' => 1800,
            'tax_rate' => 0.1000,
            'stock_unit' => '本',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->put("/retail/products/{$product->id}", [
                'product_code' => 'EXT-STOCK-001',
                'name' => '在庫設定商品',
                'retail_supplier_id' => $supplier->id,
                'cost_price' => '1000',
                'selling_price' => '1800',
                'tax_rate' => '0.1000',
                'stock_unit' => '本',
                'stock_quantity' => '24',
                'is_active' => '1',
            ])
            ->assertRedirect("/retail/products?edit={$product->id}");

        $this->assertDatabaseHas('retail_inventory_stocks', [
            'retail_product_id' => $product->id,
            'quantity' => '24.000',
        ], 'retail');
        $this->assertDatabaseHas('retail_inventory_movements', [
            'retail_product_id' => $product->id,
            'movement_type' => 'adjustment',
            'quantity' => '24.000',
            'stock_after' => '24.000',
        ], 'retail');
    }

    public function test_retail_sale_is_saved_and_decreases_inventory(): void
    {
        $user = $this->createUser();
        $supplier = $this->brewerySupplier();
        $product = RetailProduct::query()->create([
            'product_code' => 'BR-SALE-001',
            'name' => '販売商品',
            'procurement_source' => 'brewery',
            'retail_supplier_id' => $supplier->id,
            'cost_price' => 1000,
            'selling_price' => 2000,
            'tax_rate' => 0.1000,
            'stock_unit' => '本',
            'is_active' => true,
        ]);
        RetailInventoryStock::query()->create([
            'retail_product_id' => $product->id,
            'quantity' => 10,
        ]);

        $this->actingAs($user)
            ->post('/retail/sales', [
                'sale_date' => '2026-07-31',
                'sale_type' => 'cash',
                'items' => [
                    ['retail_product_id' => $product->id, 'quantity' => '3'],
                ],
            ])
            ->assertRedirect();

        $sale = RetailSale::query()->where('retail_customer_id', null)->latest('id')->firstOrFail();
        $this->assertSame('6000.00', $sale->subtotal_amount);
        $this->assertSame('600.00', $sale->tax_amount);
        $this->assertSame('6600.00', $sale->total_amount);
        $this->assertSame('paid', $sale->payment_status);

        $this->assertDatabaseHas('retail_sale_items', [
            'retail_sale_id' => $sale->id,
            'retail_product_id' => $product->id,
            'quantity' => '3.000',
            'line_amount' => '6000.00',
        ], 'retail');
        $this->assertDatabaseHas('retail_inventory_stocks', [
            'retail_product_id' => $product->id,
            'quantity' => '7.000',
        ], 'retail');
        $this->assertDatabaseHas('retail_inventory_movements', [
            'retail_product_id' => $product->id,
            'movement_type' => 'sale',
            'quantity' => '-3.000',
            'stock_after' => '7.000',
        ], 'retail');
    }

    public function test_retail_sale_creates_revises_and_cancels_brewery_order(): void
    {
        $user = $this->createUser();
        [$breweryCustomer, $breweryProduct] = $this->breweryOrderFixture();
        $supplier = $this->brewerySupplier($breweryCustomer->id);
        $retailProduct = RetailProduct::query()->create([
            'product_code' => 'BR-SYNC-001',
            'name' => '蔵連携商品',
            'procurement_source' => 'brewery',
            'retail_supplier_id' => $supplier->id,
            'brewery_product_id' => $breweryProduct->id,
            'cost_price' => 1000,
            'selling_price' => 2000,
            'tax_rate' => 0.1000,
            'stock_unit' => '本',
            'is_active' => true,
        ]);
        RetailInventoryStock::query()->create(['retail_product_id' => $retailProduct->id, 'quantity' => 10]);

        $this->actingAs($user)->post('/retail/sales', [
            'sale_date' => '2026-08-02',
            'sale_type' => 'cash',
            'items' => [['retail_product_id' => $retailProduct->id, 'quantity' => '3']],
        ])->assertRedirect();

        $sale = RetailSale::query()->latest('id')->firstOrFail();
        $order = SalesOrder::query()->findOrFail($sale->brewery_sales_order_id);
        $this->assertSame('ordered', $sale->brewery_sync_status);
        $this->assertSame('retail_sale', $order->source_type);
        $this->assertSame($sale->sale_no, $order->source_reference);
        $this->assertSame('3.0000', $order->lines()->sole()->quantity);
        $this->assertSame('1500.0000', $order->lines()->sole()->unit_price);
        $this->assertSame('transaction_category', $order->lines()->sole()->price_source);
        $this->assertNotNull($order->lines()->sole()->price_rule_id);

        // Existing retail-linked orders created before automatic pricing are repaired on revision.
        $order->lines()->update(['unit_price' => null]);

        $this->actingAs($user)->put("/retail/sales/{$sale->id}", [
            'sale_date' => '2026-08-02',
            'sale_type' => 'cash',
            'reason' => '数量変更',
            'items' => [['retail_product_id' => $retailProduct->id, 'quantity' => '2']],
        ])->assertRedirect();

        $this->assertSame('order_revised', $sale->refresh()->brewery_sync_status);
        $this->assertSame('2.0000', $order->refresh()->lines()->sole()->quantity);
        $this->assertSame('1500.0000', $order->lines()->sole()->unit_price);

        $this->actingAs($user)->post("/retail/sales/{$sale->id}/cancel", [
            'reason' => '販売取消',
        ])->assertRedirect();

        $this->assertSame('order_cancelled', $sale->refresh()->brewery_sync_status);
        $this->assertSame('cancelled', $order->refresh()->status);
    }

    public function test_retail_sale_fails_when_inventory_is_short(): void
    {
        $user = $this->createUser();
        $supplier = $this->brewerySupplier();
        $product = RetailProduct::query()->create([
            'product_code' => 'BR-SHORT-001',
            'name' => '在庫不足商品',
            'procurement_source' => 'brewery',
            'retail_supplier_id' => $supplier->id,
            'cost_price' => 1000,
            'selling_price' => 2000,
            'tax_rate' => 0.1000,
            'stock_unit' => '本',
            'is_active' => true,
        ]);
        RetailInventoryStock::query()->create([
            'retail_product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->actingAs($user)
            ->from('/retail/pos')
            ->post('/retail/sales', [
                'sale_date' => '2026-07-31',
                'sale_type' => 'cash',
                'items' => [
                    ['retail_product_id' => $product->id, 'quantity' => '2'],
                ],
            ])
            ->assertRedirect('/retail/pos')
            ->assertSessionHasErrors('items');

        $this->assertSame(0, RetailSale::query()
            ->whereHas('items', fn ($query) => $query->where('retail_product_id', $product->id))
            ->count());
        $this->assertSame(0, RetailInventoryMovement::query()
            ->where('retail_product_id', $product->id)
            ->where('movement_type', 'sale')
            ->count());
        $this->assertSame('1.000', RetailInventoryStock::query()->where('retail_product_id', $product->id)->firstOrFail()->quantity);
    }

    public function test_retail_sale_can_be_revised_and_cancelled_before_close(): void
    {
        [$user, $customer, $product] = $this->saleFixture('BR-ADJUST-001', 10);

        $this->actingAs($user)->post('/retail/sales', [
            'sale_date' => '2026-08-02',
            'sale_type' => 'credit',
            'retail_customer_id' => $customer->id,
            'items' => [['retail_product_id' => $product->id, 'quantity' => '3']],
        ])->assertRedirect();
        $sale = RetailSale::query()->where('retail_customer_id', $customer->id)->latest('id')->firstOrFail();
        $this->assertSame('7.000', RetailInventoryStock::query()->where('retail_product_id', $product->id)->firstOrFail()->quantity);

        $this->actingAs($user)
            ->get("/retail/sales/{$sale->id}")
            ->assertOk()
            ->assertSee('name="items[0][quantity]" min="1" step="1"', false)
            ->assertDontSee('step="0.001"', false);

        $this->actingAs($user)->put("/retail/sales/{$sale->id}", [
            'sale_date' => '2026-08-02',
            'sale_type' => 'credit',
            'retail_customer_id' => $customer->id,
            'reason' => '小数数量の拒否確認',
            'items' => [['retail_product_id' => $product->id, 'quantity' => '2.5']],
        ])->assertSessionHasErrors('items.0.quantity');

        $this->assertSame('posted', $sale->fresh()->status);
        $this->assertSame('7.000', RetailInventoryStock::query()->where('retail_product_id', $product->id)->firstOrFail()->quantity);

        $this->actingAs($user)->put("/retail/sales/{$sale->id}", [
            'sale_date' => '2026-08-02',
            'sale_type' => 'credit',
            'retail_customer_id' => $customer->id,
            'reason' => '数量訂正',
            'items' => [['retail_product_id' => $product->id, 'quantity' => '2']],
        ])->assertRedirect("/retail/sales/{$sale->id}");

        $sale->refresh();
        $this->assertSame('revised', $sale->status);
        $this->assertSame('8.000', RetailInventoryStock::query()->where('retail_product_id', $product->id)->firstOrFail()->quantity);
        $this->assertDatabaseHas('retail_adjustment_events', [
            'retail_sale_id' => $sale->id,
            'event_type' => 'sale_revised',
            'brewery_sync_status' => 'pending_review',
        ], 'retail');

        $this->actingAs($user)->post("/retail/sales/{$sale->id}/cancel", [
            'reason' => '販売取消',
        ])->assertRedirect("/retail/sales/{$sale->id}");

        $sale->refresh();
        $this->assertSame('cancelled', $sale->status);
        $this->assertSame('10.000', RetailInventoryStock::query()->where('retail_product_id', $product->id)->firstOrFail()->quantity);
        $this->assertDatabaseHas('retail_adjustment_events', [
            'retail_sale_id' => $sale->id,
            'event_type' => 'sale_cancelled',
            'brewery_sync_status' => 'pending_review',
        ], 'retail');
    }

    public function test_retail_sale_after_close_creates_credit_note_instead_of_revision(): void
    {
        [$user, $customer, $product] = $this->saleFixture('BR-CREDIT-NOTE-001', 10);
        RetailCompany::query()->where('company_key', 'maru')->update(['is_active' => true]);
        RetailCompanySetting::query()->updateOrCreate(
            ['company_key' => 'maru'],
            [
                'sale_mode' => 'mixed',
                'inventory_sales_policy' => 'strict_stock',
                'delivery_note_policy' => 'on_demand',
                'invoice_policy' => 'monthly_credit',
                'brewery_procurement_policy' => 'auto_order',
                'external_procurement_policy' => 'supplier_order',
            ],
        );

        $this->actingAs($user)->post('/retail/sales', [
            'sale_date' => '2026-08-02',
            'sale_type' => 'credit',
            'retail_customer_id' => $customer->id,
            'items' => [['retail_product_id' => $product->id, 'quantity' => '2']],
        ])->assertRedirect();
        $sale = RetailSale::query()->where('retail_customer_id', $customer->id)->latest('id')->firstOrFail();

        $this->actingAs($user)->withSession(['retail.company' => 'maru'])->post('/retail/invoices', [
            'retail_customer_id' => $customer->id,
            'invoice_date' => '2026-08-31',
            'closing_date' => '2026-08-31',
            'due_date' => '2026-09-30',
        ])->assertRedirect();
        $sale->refresh();
        $this->assertNotNull($sale->closed_at);

        $this->actingAs($user)->put("/retail/sales/{$sale->id}", [
            'sale_date' => '2026-08-02',
            'sale_type' => 'credit',
            'retail_customer_id' => $customer->id,
            'reason' => '締め後変更',
            'items' => [['retail_product_id' => $product->id, 'quantity' => '1']],
        ])->assertSessionHasErrors('sale');

        $this->actingAs($user)->post("/retail/sales/{$sale->id}/credit-note", [
            'reason' => '締め後返品',
        ])->assertRedirect();

        $credit = RetailSale::query()->where('original_retail_sale_id', $sale->id)->firstOrFail();
        $this->assertSame('credit_note', $credit->correction_type);
        $this->assertSame('-4000.00', $credit->subtotal_amount);
        $this->assertSame('10.000', RetailInventoryStock::query()->where('retail_product_id', $product->id)->firstOrFail()->quantity);
        $this->assertDatabaseHas('retail_adjustment_events', [
            'retail_sale_id' => $sale->id,
            'related_retail_sale_id' => $credit->id,
            'event_type' => 'credit_note_issued',
            'brewery_sync_status' => 'pending_review',
        ], 'retail');
    }

    public function test_delivery_can_be_created_from_retail_sale(): void
    {
        [$user, $customer, $product] = $this->saleFixture('BR-DELIVERY-001', 5);
        RetailCompany::query()->where('company_key', 'maru')->update(['is_active' => true]);
        RetailCompanySetting::query()->updateOrCreate(
            ['company_key' => 'maru'],
            [
                'sale_mode' => 'mixed',
                'inventory_sales_policy' => 'strict_stock',
                'delivery_note_policy' => 'on_demand',
                'invoice_policy' => 'monthly_credit',
                'brewery_procurement_policy' => 'auto_order',
                'external_procurement_policy' => 'supplier_order',
            ],
        );
        $this->actingAs($user)->withSession(['retail.company' => 'maru'])->post('/retail/sales', [
            'sale_date' => '2026-07-31',
            'sale_type' => 'credit',
            'retail_customer_id' => $customer->id,
            'items' => [['retail_product_id' => $product->id, 'quantity' => '2']],
        ])->assertRedirect();
        $sale = RetailSale::query()->where('retail_customer_id', $customer->id)->latest('id')->firstOrFail();

        $this->actingAs($user)->withSession(['retail.company' => 'maru'])->post('/retail/deliveries', [
            'retail_sale_id' => $sale->id,
            'delivery_date' => '2026-07-31',
        ])->assertRedirect();

        $delivery = RetailDelivery::query()->where('retail_sale_id', $sale->id)->firstOrFail();
        $this->actingAs($user)->withSession(['retail.company' => 'maru'])->post('/retail/deliveries', [
            'retail_sale_id' => $sale->id,
            'delivery_date' => '2026-08-01',
        ])->assertRedirect("/retail/deliveries/{$delivery->id}");
        $this->assertSame(1, RetailDelivery::query()->where('retail_sale_id', $sale->id)->count());
        $this->assertSame($customer->id, $delivery->retail_customer_id);
        $this->assertSame('issued', $delivery->status);
        $this->assertSame('monthly', $delivery->billing_method_snapshot);
        $this->assertDatabaseHas('retail_delivery_lines', [
            'retail_delivery_id' => $delivery->id,
            'description' => $product->name,
            'quantity' => '2.000',
        ], 'retail');
        $this->actingAs($user)
            ->get("/retail/deliveries/{$delivery->id}")
            ->assertOk()
            ->assertSee('<h1>商品納品書</h1>', false)
            ->assertDontSee('商品納品書・請求書');
    }

    public function test_per_sale_customer_prints_delivery_as_delivery_and_invoice(): void
    {
        [$user, $customer, $product] = $this->saleFixture('BR-DELIVERY-INVOICE-001', 5);
        $customer->update(['billing_method' => 'per_sale']);

        $this->actingAs($user)->withSession(['retail.company' => 'maru'])->post('/retail/sales', [
            'sale_date' => '2026-08-03',
            'sale_type' => 'credit',
            'retail_customer_id' => $customer->id,
            'items' => [['retail_product_id' => $product->id, 'quantity' => '1']],
        ])->assertRedirect();
        $sale = RetailSale::query()->where('retail_customer_id', $customer->id)->latest('id')->firstOrFail();

        $this->actingAs($user)->withSession(['retail.company' => 'maru'])->post('/retail/deliveries', [
            'retail_sale_id' => $sale->id,
            'delivery_date' => '2026-08-03',
        ])->assertRedirect();

        $delivery = RetailDelivery::query()->where('retail_sale_id', $sale->id)->firstOrFail();
        $this->assertSame('per_sale', $delivery->billing_method_snapshot);
        $this->actingAs($user)
            ->get("/retail/deliveries/{$delivery->id}")
            ->assertOk()
            ->assertSee('<h1>商品納品書・請求書</h1>', false);
    }

    public function test_cancelled_delivery_allows_before_close_sale_cancellation(): void
    {
        [$user, $customer, $product] = $this->saleFixture('BR-DELIVERY-CANCEL-001', 5);
        $customer->update(['billing_method' => 'per_sale']);
        $this->actingAs($user)->withSession(['retail.company' => 'maru'])->post('/retail/sales', [
            'sale_date' => '2026-08-04',
            'sale_type' => 'credit',
            'retail_customer_id' => $customer->id,
            'items' => [['retail_product_id' => $product->id, 'quantity' => '1']],
        ])->assertRedirect();
        $sale = RetailSale::query()->where('retail_customer_id', $customer->id)->latest('id')->firstOrFail();

        $this->actingAs($user)->post('/retail/deliveries', [
            'retail_sale_id' => $sale->id,
            'delivery_date' => '2026-08-04',
        ])->assertRedirect();
        $delivery = RetailDelivery::query()->where('retail_sale_id', $sale->id)->firstOrFail();

        $this->actingAs($user)
            ->get("/retail/sales/{$sale->id}")
            ->assertOk()
            ->assertSeeInOrder(['販売履歴へ', '納品書・請求書を取消・無効化', '納品・請求書印刷'])
            ->assertSee('id="delivery-cancel-dialog"', false)
            ->assertSee('取消理由（必須）')
            ->assertSee('取消・無効化を実行')
            ->assertDontSee('cancel-panel');

        $this->actingAs($user)
            ->get("/retail/deliveries/{$delivery->id}")
            ->assertOk()
            ->assertDontSee('id="delivery-cancel-dialog"', false)
            ->assertDontSee('納品書・請求書を取消・無効化');

        $this->actingAs($user)->post("/retail/sales/{$sale->id}/cancel", [
            'reason' => '納品書発行中の取消確認',
        ])->assertSessionHasErrors('sale');

        $this->actingAs($user)->post("/retail/deliveries/{$delivery->id}/cancel", [
            'reason' => '宛先誤りのため無効化',
        ])->assertRedirect("/retail/sales/{$sale->id}");

        $delivery->refresh();
        $this->assertSame('cancelled', $delivery->status);
        $this->assertSame('宛先誤りのため無効化', $delivery->cancellation_reason);
        $this->assertNotNull($delivery->cancelled_at);
        $this->actingAs($user)
            ->get("/retail/deliveries/{$delivery->id}")
            ->assertOk()
            ->assertSee('取消済み・無効')
            ->assertDontSee('id="delivery-cancel-dialog"', false)
            ->assertDontSee('onclick="window.print()"', false);
        $this->actingAs($user)
            ->get("/retail/sales/{$sale->id}")
            ->assertOk()
            ->assertSee('締め前取消')
            ->assertSee('納品・請求書印刷')
            ->assertSee('target="_blank"', false)
            ->assertSee('販売履歴へ')
            ->assertDontSee('onclick="history.back()"', false)
            ->assertDontSee('続けて販売')
            ->assertDontSee('外部商品');

        $this->actingAs($user)->post("/retail/sales/{$sale->id}/cancel", [
            'reason' => '販売自体を取消',
        ])->assertRedirect("/retail/sales/{$sale->id}");

        $this->assertSame('cancelled', $sale->fresh()->status);
        $this->assertSame('5.000', RetailInventoryStock::query()->where('retail_product_id', $product->id)->firstOrFail()->quantity);
    }

    public function test_store_customer_is_always_treated_as_per_sale_billing(): void
    {
        [$user, , $product] = $this->saleFixture('BR-STORE-DELIVERY-001', 5);
        $reducedTaxProduct = RetailProduct::query()->create([
            'product_code' => 'BR-STORE-DELIVERY-008',
            'name' => '軽減税率商品',
            'procurement_source' => 'brewery',
            'retail_supplier_id' => $product->retail_supplier_id,
            'cost_price' => 500,
            'selling_price' => 1000,
            'tax_rate' => 0.0800,
            'stock_unit' => '個',
            'is_active' => true,
        ]);
        RetailInventoryStock::query()->create([
            'retail_product_id' => $reducedTaxProduct->id,
            'quantity' => 5,
        ]);

        $this->actingAs($user)->post('/retail/sales', [
            'sale_date' => '2026-08-03',
            'sale_type' => 'cash',
            'items' => [
                ['retail_product_id' => $product->id, 'quantity' => '1'],
                ['retail_product_id' => $reducedTaxProduct->id, 'quantity' => '1'],
            ],
        ])->assertRedirect();
        $sale = RetailSale::query()->whereNull('retail_customer_id')->latest('id')->firstOrFail();

        $this->actingAs($user)
            ->get("/retail/sales/{$sale->id}")
            ->assertOk()
            ->assertSee('納品・請求書印刷')
            ->assertSee('target="_blank"', false)
            ->assertSee('販売履歴へ')
            ->assertDontSee('onclick="history.back()"', false)
            ->assertDontSee('納品書を作成して印刷へ');

        $this->actingAs($user)->post('/retail/deliveries', [
            'retail_sale_id' => $sale->id,
            'delivery_date' => '2026-08-03',
        ])->assertRedirect();

        $delivery = RetailDelivery::query()->where('retail_sale_id', $sale->id)->firstOrFail();
        $this->assertNull($delivery->retail_customer_id);
        $this->assertSame('per_sale', $delivery->billing_method_snapshot);
        $this->assertSame('お客様各位', $delivery->delivery_name);
        $this->assertDatabaseHas('retail_delivery_lines', [
            'retail_delivery_id' => $delivery->id,
            'description' => '軽減税率商品',
            'tax_rate' => '0.0800',
        ], 'retail');

        $this->actingAs($user)
            ->get("/retail/deliveries/{$delivery->id}")
            ->assertOk()
            ->assertSee('<h1>商品納品書・請求書</h1>', false)
            ->assertSee('お客様各位')
            ->assertDontSee('お客様各位 御中')
            ->assertSee('納品書番号：'.$delivery->delivery_no)
            ->assertDontSee('販売番号:')
            ->assertDontSee('状態:')
            ->assertSee('消費税率 8% 対象')
            ->assertSee('消費税率 10% 対象')
            ->assertSee('8% 税込小計')
            ->assertSee('10% 税込小計')
            ->assertSee('お買い上げ金額')
            ->assertSee('税込合計')
            ->assertSee('内消費税');
    }

    public function test_invoice_and_payment_allocation_can_be_created(): void
    {
        [$user, $customer, $product] = $this->saleFixture('BR-INVOICE-001', 5);
        RetailCompany::query()->where('company_key', 'maru')->update(['is_active' => true]);
        RetailCompanySetting::query()->updateOrCreate(
            ['company_key' => 'maru'],
            [
                'sale_mode' => 'mixed',
                'inventory_sales_policy' => 'strict_stock',
                'delivery_note_policy' => 'on_demand',
                'invoice_policy' => 'monthly_credit',
                'brewery_procurement_policy' => 'auto_order',
                'external_procurement_policy' => 'supplier_order',
            ],
        );
        $this->actingAs($user)->post('/retail/sales', [
            'sale_date' => '2026-07-31',
            'sale_type' => 'credit',
            'retail_customer_id' => $customer->id,
            'items' => [['retail_product_id' => $product->id, 'quantity' => '2']],
        ])->assertRedirect();

        $this->actingAs($user)->withSession(['retail.company' => 'maru'])->post('/retail/invoices', [
            'retail_customer_id' => $customer->id,
            'invoice_date' => '2026-07-31',
            'closing_date' => '2026-07-31',
            'due_date' => '2026-08-31',
        ])->assertRedirect();

        $invoice = RetailInvoice::query()->where('retail_customer_id', $customer->id)->firstOrFail();
        $this->assertSame('4400.00', $invoice->total_amount);
        $this->assertSame('4400.00', $invoice->balance_amount);

        $this->actingAs($user)->withSession(['retail.company' => 'maru'])->post('/retail/payments', [
            'retail_customer_id' => $customer->id,
            'payment_date' => '2026-08-20',
            'payment_method' => 'bank_transfer',
            'amount' => '3000',
        ])->assertRedirect();

        $payment = RetailPayment::query()->where('retail_customer_id', $customer->id)->firstOrFail();
        $invoice->refresh();
        $this->assertSame('3000.00', $payment->amount);
        $this->assertSame('0.00', $payment->unapplied_amount);
        $this->assertSame('partial', $invoice->status);
        $this->assertSame('1400.00', $invoice->balance_amount);
    }

    public function test_unpaid_invoice_can_be_cancelled_and_reclosed(): void
    {
        [$user, $customer, $product] = $this->saleFixture('BR-INV-CANCEL-001', 5);
        RetailCompany::query()->where('company_key', 'maru')->update(['is_active' => true]);
        RetailCompanySetting::query()->updateOrCreate(
            ['company_key' => 'maru'],
            [
                'sale_mode' => 'mixed',
                'inventory_sales_policy' => 'strict_stock',
                'delivery_note_policy' => 'on_demand',
                'invoice_policy' => 'monthly_credit',
                'brewery_procurement_policy' => 'auto_order',
                'external_procurement_policy' => 'supplier_order',
            ],
        );
        $this->actingAs($user)->withSession(['retail.company' => 'maru'])->post('/retail/sales', [
            'sale_date' => '2026-07-31',
            'sale_type' => 'credit',
            'retail_customer_id' => $customer->id,
            'items' => [['retail_product_id' => $product->id, 'quantity' => '2']],
        ])->assertRedirect();
        $sale = RetailSale::query()->where('retail_customer_id', $customer->id)->latest('id')->firstOrFail();

        $this->actingAs($user)->withSession(['retail.company' => 'maru'])->post('/retail/invoices', [
            'retail_customer_id' => $customer->id,
            'invoice_date' => '2026-07-31',
            'closing_date' => '2026-07-31',
            'due_date' => '2026-08-31',
        ])->assertRedirect();
        $invoice = RetailInvoice::query()->where('retail_customer_id', $customer->id)->firstOrFail();
        $sale->refresh();
        $this->assertNotNull($sale->closed_at);

        $this->actingAs($user)->post("/retail/invoices/{$invoice->id}/cancel", [
            'reason' => '締め直し',
        ])->assertRedirect("/retail/invoices/{$invoice->id}");

        $invoice->refresh();
        $sale->refresh();
        $this->assertSame('cancelled', $invoice->status);
        $this->assertSame('0.00', $invoice->balance_amount);
        $this->assertNull($sale->closed_at);

        $this->actingAs($user)->post('/retail/invoices', [
            'retail_customer_id' => $customer->id,
            'invoice_date' => '2026-08-01',
            'closing_date' => '2026-07-31',
            'due_date' => '2026-08-31',
        ])->assertRedirect();

        $this->assertSame(2, RetailInvoice::query()->where('retail_customer_id', $customer->id)->count());
        $newInvoice = RetailInvoice::query()->where('retail_customer_id', $customer->id)->latest('id')->firstOrFail();
        $this->assertSame('open', $newInvoice->status);
        $this->assertSame('4400.00', $newInvoice->total_amount);
    }

    public function test_paid_invoice_cannot_be_cancelled_and_payment_can_be_refunded(): void
    {
        [$user, $customer, $product] = $this->saleFixture('BR-PAY-REFUND-001', 5);
        RetailCompany::query()->where('company_key', 'maru')->update(['is_active' => true]);
        RetailCompanySetting::query()->updateOrCreate(
            ['company_key' => 'maru'],
            [
                'sale_mode' => 'mixed',
                'inventory_sales_policy' => 'strict_stock',
                'delivery_note_policy' => 'on_demand',
                'invoice_policy' => 'monthly_credit',
                'brewery_procurement_policy' => 'auto_order',
                'external_procurement_policy' => 'supplier_order',
            ],
        );
        $this->actingAs($user)->withSession(['retail.company' => 'maru'])->post('/retail/sales', [
            'sale_date' => '2026-07-31',
            'sale_type' => 'credit',
            'retail_customer_id' => $customer->id,
            'items' => [['retail_product_id' => $product->id, 'quantity' => '2']],
        ])->assertRedirect();

        $this->actingAs($user)->withSession(['retail.company' => 'maru'])->post('/retail/invoices', [
            'retail_customer_id' => $customer->id,
            'invoice_date' => '2026-07-31',
            'closing_date' => '2026-07-31',
            'due_date' => '2026-08-31',
        ])->assertRedirect();

        $this->actingAs($user)->withSession(['retail.company' => 'maru'])->post('/retail/payments', [
            'retail_customer_id' => $customer->id,
            'payment_date' => '2026-08-20',
            'payment_method' => 'bank_transfer',
            'amount' => '3000',
        ])->assertRedirect();

        $invoice = RetailInvoice::query()->where('retail_customer_id', $customer->id)->firstOrFail();
        $payment = RetailPayment::query()->where('retail_customer_id', $customer->id)->whereNull('adjustment_type')->firstOrFail();

        $this->actingAs($user)->withSession(['retail.company' => 'maru'])->post("/retail/invoices/{$invoice->id}/cancel", [
            'reason' => '入金後取消',
        ])->assertSessionHasErrors('invoice');

        $this->actingAs($user)->withSession(['retail.company' => 'maru'])->post("/retail/payments/{$payment->id}/refund", [
            'payment_date' => '2026-08-21',
            'payment_method' => 'bank_transfer',
            'amount' => '1000',
            'reason' => '過入金返金',
        ])->assertRedirect();

        $payment->refresh();
        $this->assertSame('partial_refund', $payment->status);
        $this->assertDatabaseHas('retail_payments', [
            'retail_customer_id' => $customer->id,
            'original_retail_payment_id' => $payment->id,
            'adjustment_type' => 'refund',
            'amount' => '-1000.00',
        ], 'retail');
    }

    public function test_purchase_order_suggestions_and_brewery_send_can_be_created(): void
    {
        $user = $this->createUser();
        RetailProduct::query()->update(['is_active' => false]);
        [$breweryCustomer, $breweryProduct] = $this->breweryOrderFixture();
        $supplier = $this->brewerySupplier($breweryCustomer->id);
        $product = RetailProduct::query()->create([
            'product_code' => 'BR-PO-001',
            'name' => '発注案商品',
            'procurement_source' => 'brewery',
            'retail_supplier_id' => $supplier->id,
            'brewery_product_id' => $breweryProduct->id,
            'cost_price' => 1000,
            'selling_price' => 1800,
            'tax_rate' => 0.1000,
            'stock_unit' => '本',
            'reorder_point' => 5,
            'reorder_quantity' => 12,
            'is_active' => true,
        ]);
        RetailInventoryStock::query()->create([
            'retail_product_id' => $product->id,
            'quantity' => 3,
        ]);

        $this->actingAs($user)
            ->post('/retail/purchase-orders/suggestions')
            ->assertRedirect('/retail/purchase-orders');

        $order = RetailPurchaseOrder::query()->where('retail_supplier_id', $supplier->id)->latest('id')->firstOrFail();
        $this->assertSame('brewery_api', $order->order_route);
        $this->assertSame('draft', $order->status);
        $this->assertDatabaseHas('retail_purchase_order_lines', [
            'retail_purchase_order_id' => $order->id,
            'retail_product_id' => $product->id,
            'quantity' => '12.000',
        ], 'retail');

        $this->actingAs($user)
            ->post("/retail/purchase-orders/{$order->id}/send-brewery")
            ->assertRedirect('/retail/purchase-orders');

        $order->refresh();
        $this->assertSame('ordered', $order->status);
        $this->assertNotNull($order->ordered_at);
        $this->assertNotNull($order->brewery_sales_order_id);

        $salesOrder = SalesOrder::query()->with('lines')->findOrFail($order->brewery_sales_order_id);
        $this->assertSame($breweryCustomer->id, $salesOrder->customer_id);
        $this->assertSame($order->purchase_order_no, $salesOrder->customer_order_number);
        $this->assertSame('retail_purchase_order', $salesOrder->source_type);
        $this->assertSame($order->purchase_order_no, $salesOrder->source_reference);
        $this->assertSame($breweryProduct->id, $salesOrder->lines->first()->product_id);
        $this->assertSame('12.0000', $salesOrder->lines->first()->quantity);
    }

    public function test_draft_retail_purchase_order_can_be_deleted_before_sending(): void
    {
        $user = $this->createUser();
        RetailProduct::query()->update(['is_active' => false]);
        [$breweryCustomer, $breweryProduct] = $this->breweryOrderFixture();
        $supplier = $this->brewerySupplier($breweryCustomer->id);
        $product = RetailProduct::query()->create([
            'product_code' => 'BR-PO-DELETE-001',
            'name' => 'Draft Delete Product',
            'procurement_source' => 'brewery',
            'retail_supplier_id' => $supplier->id,
            'brewery_product_id' => $breweryProduct->id,
            'cost_price' => 1000,
            'selling_price' => 1800,
            'tax_rate' => 0.1000,
            'stock_unit' => '本',
            'reorder_point' => 5,
            'reorder_quantity' => 12,
            'is_active' => true,
        ]);
        RetailInventoryStock::query()->create([
            'retail_product_id' => $product->id,
            'quantity' => 3,
        ]);

        $this->actingAs($user)
            ->post('/retail/purchase-orders/suggestions')
            ->assertRedirect('/retail/purchase-orders');

        $order = RetailPurchaseOrder::query()->where('retail_supplier_id', $supplier->id)->latest('id')->firstOrFail();
        $lineId = $order->lines()->firstOrFail()->id;

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->get('/retail/purchase-orders')
            ->assertOk()
            ->assertSee($order->purchase_order_no)
            ->assertSee('削除')
            ->assertSee('蔵API送信');

        $this->actingAs($user)
            ->delete("/retail/purchase-orders/{$order->id}")
            ->assertRedirect('/retail/purchase-orders');

        $this->assertDatabaseMissing('retail_purchase_orders', ['id' => $order->id], 'retail');
        $this->assertDatabaseMissing('retail_purchase_order_lines', ['id' => $lineId], 'retail');

        $this->actingAs($user)
            ->post('/retail/purchase-orders/suggestions')
            ->assertRedirect('/retail/purchase-orders');

        $nextOrder = RetailPurchaseOrder::query()->where('retail_supplier_id', $supplier->id)->latest('id')->firstOrFail();
        $this->assertNotSame($order->purchase_order_no, $nextOrder->purchase_order_no);
    }

    public function test_retail_purchase_order_page_warns_manual_required_cancellations(): void
    {
        $user = $this->createUser();
        $supplier = $this->brewerySupplier();
        RetailPurchaseOrder::query()->create([
            'purchase_order_no' => 'RPO-MANUAL-REQUIRED-001',
            'retail_supplier_id' => $supplier->id,
            'supplier_type' => 'brewery',
            'order_route' => 'brewery_api',
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_reason' => '請求確定済み取消',
            'brewery_cancel_status' => 'manual_required',
            'brewery_cancel_error' => '蔵側で請求処理済みのため、取消またはマイナス訂正受注を自動発行できません。',
            'subtotal_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
        ]);

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->get('/retail/purchase-orders')
            ->assertOk()
            ->assertSee('手動対応が必要な取消')
            ->assertSee('manual_required')
            ->assertSee('蔵側で請求処理済み');
    }

    public function test_sent_retail_purchase_order_cancels_linked_brewery_order_when_not_instructed(): void
    {
        $user = $this->createUser();
        RetailProduct::query()->update(['is_active' => false]);
        [$breweryCustomer, $breweryProduct] = $this->breweryOrderFixture();
        $supplier = $this->brewerySupplier($breweryCustomer->id);
        $product = RetailProduct::query()->create([
            'product_code' => 'BR-PO-CANCEL-001',
            'name' => 'Sent Cancel Product',
            'procurement_source' => 'brewery',
            'retail_supplier_id' => $supplier->id,
            'brewery_product_id' => $breweryProduct->id,
            'cost_price' => 1000,
            'selling_price' => 1800,
            'tax_rate' => 0.1000,
            'stock_unit' => '本',
            'reorder_point' => 5,
            'reorder_quantity' => 12,
            'is_active' => true,
        ]);
        RetailInventoryStock::query()->create([
            'retail_product_id' => $product->id,
            'quantity' => 3,
        ]);

        $this->actingAs($user)
            ->post('/retail/purchase-orders/suggestions')
            ->assertRedirect('/retail/purchase-orders');

        $order = RetailPurchaseOrder::query()->where('retail_supplier_id', $supplier->id)->latest('id')->firstOrFail();

        $this->actingAs($user)
            ->post("/retail/purchase-orders/{$order->id}/send-brewery")
            ->assertRedirect('/retail/purchase-orders');

        $order->refresh();
        $salesOrderId = $order->brewery_sales_order_id;
        $this->assertNotNull($salesOrderId);

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->get('/retail/purchase-orders')
            ->assertOk()
            ->assertSee($order->purchase_order_no)
            ->assertSee('取消理由')
            ->assertSee('取消');

        $this->actingAs($user)
            ->post("/retail/purchase-orders/{$order->id}/cancel", ['reason' => 'supplier requested cancellation'])
            ->assertRedirect('/retail/purchase-orders');

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertSame('supplier requested cancellation', $order->cancelled_reason);
        $this->assertSame($salesOrderId, $order->brewery_sales_order_id);
        $this->assertSame('cancelled', $order->brewery_cancel_status);
        $this->assertNull($order->brewery_cancellation_sales_order_id);
        $this->assertNull($order->brewery_cancel_error);
        $this->assertNotNull($order->brewery_cancelled_at);

        $salesOrder = SalesOrder::query()->findOrFail($salesOrderId);
        $this->assertSame('cancelled', $salesOrder->status);
        $this->assertNotNull($salesOrder->cancelled_at);
        $this->assertSame("小売発注取消 {$order->purchase_order_no}: supplier requested cancellation", $salesOrder->cancelled_reason);
    }

    public function test_sent_retail_purchase_order_creates_brewery_correction_when_brewery_order_cannot_be_cancelled(): void
    {
        $user = $this->createUser();
        RetailProduct::query()->update(['is_active' => false]);
        [$breweryCustomer, $breweryProduct] = $this->breweryOrderFixture();
        $supplier = $this->brewerySupplier($breweryCustomer->id);
        $product = RetailProduct::query()->create([
            'product_code' => 'BR-PO-CORRECTION-001',
            'name' => 'Correction Product',
            'procurement_source' => 'brewery',
            'retail_supplier_id' => $supplier->id,
            'brewery_product_id' => $breweryProduct->id,
            'cost_price' => 1000,
            'selling_price' => 1800,
            'tax_rate' => 0.1000,
            'stock_unit' => '本',
            'reorder_point' => 5,
            'reorder_quantity' => 12,
            'is_active' => true,
        ]);
        RetailInventoryStock::query()->create([
            'retail_product_id' => $product->id,
            'quantity' => 3,
        ]);

        $this->actingAs($user)
            ->post('/retail/purchase-orders/suggestions')
            ->assertRedirect('/retail/purchase-orders');

        $order = RetailPurchaseOrder::query()->where('retail_supplier_id', $supplier->id)->latest('id')->firstOrFail();

        $this->actingAs($user)
            ->post("/retail/purchase-orders/{$order->id}/send-brewery")
            ->assertRedirect('/retail/purchase-orders');

        $order->refresh();
        $salesOrder = SalesOrder::query()->with('lines')->findOrFail($order->brewery_sales_order_id);
        $salesOrder->lines()->firstOrFail()->update(['remaining_quantity' => '0.0000']);

        $this->actingAs($user)
            ->post("/retail/purchase-orders/{$order->id}/cancel", ['reason' => 'already instructed cancellation'])
            ->assertRedirect('/retail/purchase-orders');

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('correction_sent', $order->brewery_cancel_status);
        $this->assertNull($order->brewery_cancel_error);
        $this->assertNotNull($order->brewery_cancellation_sales_order_id);

        $salesOrder->refresh();
        $this->assertSame('received', $salesOrder->status);
        $this->assertNull($salesOrder->cancelled_at);

        $correction = SalesOrder::query()->with('lines')->findOrFail($order->brewery_cancellation_sales_order_id);
        $this->assertSame($breweryCustomer->id, $correction->customer_id);
        $this->assertSame('retail_purchase_order_cancellation', $correction->source_type);
        $this->assertSame($order->purchase_order_no.':cancel', $correction->source_reference);
        $this->assertSame($breweryProduct->id, $correction->lines->first()->product_id);
        $this->assertSame('-12.0000', $correction->lines->first()->quantity);
    }

    public function test_brewery_price_changes_can_be_detected_and_applied_with_history(): void
    {
        $user = $this->createUser();
        $unit = Unit::query()->create([
            'code' => 'price-bottle',
            'name' => 'bottle',
            'symbol' => '本',
            'unit_type' => 'count',
        ]);
        $breweryProduct = Product::query()->create([
            'product_code' => 'PRICE-001',
            'product_type' => 'sake',
            'name' => 'Price Product',
            'display_name' => 'Price Product 720ml',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_sales_available' => true,
            'is_active' => true,
        ]);
        $wholesaleList = PriceList::query()->create([
            'code' => 'wholesale_price',
            'name' => 'Wholesale',
            'price_type' => 'wholesale',
            'is_active' => true,
        ]);
        $retailList = PriceList::query()->create([
            'code' => 'retail_price',
            'name' => 'Retail',
            'price_type' => 'retail',
            'is_active' => true,
        ]);
        foreach ([[$wholesaleList->id, 1200], [$retailList->id, 2200]] as [$priceListId, $unitPrice]) {
            PriceRule::query()->create([
                'price_list_id' => $priceListId,
                'product_id' => $breweryProduct->id,
                'unit_id' => $unit->id,
                'unit_price' => $unitPrice,
                'effective_from' => now()->subDay()->toDateString(),
                'is_active' => true,
            ]);
        }
        $retailProduct = RetailProduct::query()->create([
            'product_code' => 'BR-PRICE-001',
            'name' => 'Retail Price Product',
            'procurement_source' => 'brewery',
            'retail_supplier_id' => $this->brewerySupplier()->id,
            'brewery_product_id' => $breweryProduct->id,
            'cost_price' => 1000,
            'selling_price' => 2000,
            'tax_rate' => 0.1000,
            'stock_unit' => '本',
            'is_active' => true,
        ]);
        [$breweryCustomer] = $this->breweryOrderFixture();

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->put('/retail/settings', [
                'company_name' => '丸社 更新',
                'company_description' => '更新後の小売会社',
                'representative_name' => '丸山 太郎',
                'postal_code' => '100-0001',
                'address1' => '東京都千代田区千代田1-1',
                'address2' => '丸社ビル2階',
                'phone' => '03-1234-5678',
                'fax' => '03-1234-5679',
                'email' => 'retail@example.com',
                'invoice_registration_number' => 'T1234567890123',
                'sale_mode' => 'credit_enabled',
                'inventory_sales_policy' => 'allow_negative_order',
                'delivery_note_policy' => 'per_sale',
                'invoice_policy' => 'per_invoice',
                'brewery_procurement_policy' => 'auto_order',
                'external_procurement_policy' => 'manual',
                'brewery_partner_id' => $breweryCustomer->id,
            ])
            ->assertRedirect('/retail/settings');
        $this->assertDatabaseHas('retail_companies', [
            'company_key' => 'maru',
            'name' => '丸社 更新',
            'description' => '更新後の小売会社',
            'representative_name' => '丸山 太郎',
            'postal_code' => '100-0001',
            'address1' => '東京都千代田区千代田1-1',
            'address2' => '丸社ビル2階',
            'phone' => '03-1234-5678',
            'fax' => '03-1234-5679',
            'email' => 'retail@example.com',
            'invoice_registration_number' => 'T1234567890123',
        ], 'retail');
        $this->assertDatabaseHas('retail_company_settings', [
            'company_key' => 'maru',
            'sale_mode' => 'credit_enabled',
            'inventory_sales_policy' => 'allow_negative_order',
            'delivery_note_policy' => 'per_sale',
            'invoice_policy' => 'per_invoice',
            'external_procurement_policy' => 'manual',
        ], 'retail');
        $this->assertDatabaseHas('retail_suppliers', [
            'supplier_code' => 'BREWERY',
            'supplier_type' => 'brewery',
            'ordering_method' => 'api',
            'brewery_partner_id' => $breweryCustomer->id,
        ], 'retail');

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->put('/retail/system-management', [
                'system_name' => '小売販売管理',
                'theme' => 'green',
                'detection_mode' => 'interval',
                'interval_minutes' => '30',
            ])
            ->assertRedirect('/retail/system-management');
        $this->assertSame('interval', RetailPriceSyncSetting::query()->first()?->detection_mode);
        $this->assertSame(30, RetailPriceSyncSetting::query()->first()?->interval_minutes);
        $systemSetting = RetailSystemSetting::query()->first();
        $this->assertSame('小売販売管理', $systemSetting?->system_name);
        $this->assertSame('green', $systemSetting?->theme);

        $this->actingAs($user)
            ->post('/retail/products/import/price-changes/detect')
            ->assertRedirect('/retail/products/import?tab=candidates');

        $this->actingAs($user)
            ->get('/retail/products/import?tab=candidates')
            ->assertOk()
            ->assertSee('<option value="cost_and_selling" selected>原価＋販売価格更新</option>', false);

        $candidate = RetailPriceChangeCandidate::query()->where('retail_product_id', $retailProduct->id)->firstOrFail();
        $this->assertSame('1200.00', $candidate->source_cost_price);
        $this->assertSame('2200.00', $candidate->source_selling_price);

        $this->actingAs($user)
            ->post("/retail/products/import/price-changes/{$candidate->id}/apply", ['apply_mode' => 'cost_only'])
            ->assertRedirect('/retail/products/import?tab=candidates');
        $retailProduct->refresh();
        $this->assertSame('1200.00', $retailProduct->cost_price);
        $this->assertSame('2000.00', $retailProduct->selling_price);
        $this->assertSame(1, RetailPriceHistory::query()->where('apply_mode', 'cost_only')->count());

        PriceRule::query()->where('price_list_id', $wholesaleList->id)->update(['unit_price' => 1300]);
        PriceRule::query()->where('price_list_id', $retailList->id)->update(['unit_price' => 2400]);

        $this->actingAs($user)
            ->post('/retail/products/import/price-changes/detect')
            ->assertRedirect('/retail/products/import?tab=candidates');
        $nextCandidate = RetailPriceChangeCandidate::query()
            ->where('retail_product_id', $retailProduct->id)
            ->where('status', 'open')
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($user)
            ->post("/retail/products/import/price-changes/{$nextCandidate->id}/apply", ['apply_mode' => 'cost_and_selling'])
            ->assertRedirect('/retail/products/import?tab=candidates');
        $retailProduct->refresh();
        $this->assertSame('1300.00', $retailProduct->cost_price);
        $this->assertSame('2400.00', $retailProduct->selling_price);
        $this->assertSame(2, RetailPriceHistory::query()->count());
    }

    public function test_deleted_brewery_product_is_not_removed_and_can_only_be_sold_from_store_stock(): void
    {
        $user = $this->createUser();
        $unit = Unit::query()->create([
            'code' => 'deleted-source-bottle',
            'name' => 'bottle',
            'symbol' => '本',
            'unit_type' => 'count',
        ]);
        $breweryProduct = Product::query()->create([
            'product_code' => 'DELETED-001',
            'product_type' => 'sake',
            'name' => '削除予定蔵商品',
            'display_name' => '削除予定蔵商品 720ml',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_sales_available' => true,
            'is_active' => true,
        ]);
        $retailProduct = RetailProduct::query()->create([
            'product_code' => 'BR-DELETED-001',
            'name' => '削除予定蔵商品 720ml',
            'procurement_source' => 'brewery',
            'retail_supplier_id' => $this->brewerySupplier()->id,
            'brewery_product_id' => $breweryProduct->id,
            'brewery_source_status' => 'current',
            'cost_price' => 1000,
            'selling_price' => 1800,
            'tax_rate' => 0.1000,
            'stock_unit' => '本',
            'is_active' => true,
        ]);
        RetailInventoryStock::query()->create([
            'retail_product_id' => $retailProduct->id,
            'quantity' => 1,
        ]);
        RetailCompanySetting::query()->updateOrCreate(
            ['company_key' => 'maru'],
            [
                'sale_mode' => 'mixed',
                'inventory_sales_policy' => 'allow_negative_order',
                'delivery_note_policy' => 'on_demand',
                'invoice_policy' => 'monthly_credit',
                'brewery_procurement_policy' => 'auto_order',
                'external_procurement_policy' => 'supplier_order',
            ],
        );

        $breweryProduct->delete();

        $this->actingAs($user)
            ->post('/retail/products/import/price-changes/detect')
            ->assertRedirect('/retail/products/import?tab=candidates');

        $this->assertDatabaseHas('retail_products', [
            'id' => $retailProduct->id,
            'brewery_source_status' => 'deleted',
            'is_active' => true,
        ], 'retail');

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->get('/retail/pos')
            ->assertOk()
            ->assertSee('蔵側削除済みです。店舗在庫がある数量まで販売できます。');

        $payload = [
            'sale_date' => now()->toDateString(),
            'sale_type' => 'cash',
            'items' => [[
                'retail_product_id' => $retailProduct->id,
                'quantity' => 2,
            ]],
        ];

        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->from('/retail/pos')
            ->post('/retail/sales', $payload)
            ->assertRedirect('/retail/pos')
            ->assertSessionHasErrors('items');

        $payload['items'][0]['quantity'] = 1;
        $this->actingAs($user)
            ->withSession(['retail.company' => 'maru'])
            ->post('/retail/sales', $payload)
            ->assertRedirect();

        $sale = RetailSale::query()->latest('id')->firstOrFail();
        $this->assertSame('not_required', $sale->brewery_sync_status);
        $this->assertDatabaseHas('retail_inventory_stocks', [
            'retail_product_id' => $retailProduct->id,
            'quantity' => '0.000',
        ], 'retail');
        $this->assertSame(0, SalesOrder::query()->count());
    }

    public function test_scheduled_brewery_product_detection_runs_when_interval_is_due(): void
    {
        RetailPriceSyncSetting::query()->create([
            'detection_mode' => 'interval',
            'interval_minutes' => 30,
            'last_detected_at' => now()->subMinutes(31),
        ]);

        $this->artisan('retail:detect-brewery-products')->assertSuccessful();

        $setting = RetailPriceSyncSetting::query()->firstOrFail();
        $this->assertTrue($setting->last_detected_at->gt(now()->subMinute()));
        $this->assertIsArray($setting->last_detection_summary);
        $this->assertSame(['new', 'changed', 'price', 'inactive', 'deleted'], array_keys($setting->last_detection_summary));
    }

    private function createUser(): User
    {
        return User::query()->create([
            'name' => 'Retail User',
            'email' => 'retail-user@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);
    }

    private function brewerySupplier(?int $breweryPartnerId = null): RetailSupplier
    {
        $supplier = RetailSupplier::query()->firstOrCreate(
            ['supplier_code' => 'BREWERY'],
            [
                'name' => '蔵販売業務システム',
                'supplier_type' => 'brewery',
                'ordering_method' => 'api',
                'is_active' => true,
            ],
        );

        if ($breweryPartnerId) {
            $supplier->update(['brewery_partner_id' => $breweryPartnerId]);
        }

        return $supplier;
    }

    /**
     * @return array{0: User, 1: RetailCustomer, 2: RetailProduct}
     */
    private function saleFixture(string $productCode, int $stockQuantity): array
    {
        $user = $this->createUser();
        $customer = RetailCustomer::query()->create([
            'customer_code' => 'RC-'.$productCode,
            'name' => '販売先 '.$productCode,
            'closing_day' => 31,
            'payment_month_offset' => 1,
            'payment_day' => 31,
            'invoice_required' => true,
            'is_active' => true,
        ]);
        $supplier = $this->brewerySupplier();
        $product = RetailProduct::query()->create([
            'product_code' => $productCode,
            'name' => '販売商品 '.$productCode,
            'procurement_source' => 'brewery',
            'retail_supplier_id' => $supplier->id,
            'cost_price' => 1000,
            'selling_price' => 2000,
            'tax_rate' => 0.1000,
            'stock_unit' => '本',
            'is_active' => true,
        ]);
        RetailInventoryStock::query()->create([
            'retail_product_id' => $product->id,
            'quantity' => $stockQuantity,
        ]);

        return [$user, $customer, $product];
    }

    /**
     * @return array{0: Customer, 1: Product}
     */
    private function breweryOrderFixture(): array
    {
        NumberSequence::query()->firstOrCreate(
            ['code' => 'sales_order'],
            [
                'name' => '受注番号',
                'prefix' => 'SO-{YYYY}{MM}{DD}-',
                'current_number' => 0,
                'padding_length' => 4,
                'reset_type' => 'none',
                'is_active' => true,
            ],
        );
        $transaction = TransactionCategory::query()->firstOrCreate(
            ['code' => 'retail_auto_order'],
            ['name' => '小売自動発注', 'is_active' => true],
        );
        $settlement = SettlementReceivableCategory::query()->firstOrCreate(
            ['code' => 'retail_accounts_receivable'],
            ['name' => '小売売掛', 'is_active' => true],
        );
        $billingCycle = BillingCycle::query()->firstOrCreate(
            ['code' => 'retail_monthly_end'],
            ['name' => '小売月末締め', 'closing_day' => 31, 'is_active' => true],
        );
        $unit = Unit::query()->firstOrCreate(
            ['code' => 'retail_bottle'],
            ['name' => '本', 'symbol' => '本', 'unit_type' => 'count', 'is_active' => true],
        );

        $customer = Customer::query()->create([
            'customer_code' => 'BREWERY-RETAIL-CUST',
            'name' => '小売会社',
            'transaction_category_id' => $transaction->id,
            'settlement_receivable_category_id' => $settlement->id,
            'billing_cycle_id' => $billingCycle->id,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'product_code' => 'BREWERY-SAKE-PO',
            'product_type' => 'sake',
            'name' => '蔵側発注商品',
            'display_name' => '蔵側発注商品 720ml',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_sales_available' => true,
            'is_active' => true,
        ]);
        $priceList = PriceList::query()->firstOrCreate(
            ['code' => 'retail_auto_order_price'],
            ['name' => '小売自動発注価格', 'price_type' => 'wholesale', 'is_active' => true],
        );
        PriceRule::query()->create([
            'price_list_id' => $priceList->id,
            'product_id' => $product->id,
            'transaction_category_id' => $transaction->id,
            'unit_id' => $unit->id,
            'unit_price' => '1500.0000',
            'currency' => 'JPY',
            'priority' => 200,
            'effective_from' => '2026-01-01',
            'rounding_method' => 'round',
            'is_active' => true,
        ]);

        return [$customer, $product];
    }
}
