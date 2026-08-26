<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\ConsumptionTaxCategory;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\PriceRule;
use App\Models\Role;
use App\Models\SettlementReceivableCategory;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\CustomerMasterSeeder;
use Database\Seeders\FoundationPermissionSeeder;
use Database\Seeders\PriceMasterSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\TaxMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductItemMasterApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_search_ignores_full_width_half_width_and_spaces(): void
    {
        [$unit, $tax] = $this->signInAsAdmin();
        $productId = $this->postJson('/api/v1/masters/products', $this->productPayload($unit, $tax))
            ->assertCreated()
            ->assertJsonPath('data.product.product_code', 'ＣＵＰ００１')
            ->json('data.product.id');

        $this->getJson('/api/v1/masters/products?q='.urlencode('cup 001'))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.products.0.id', $productId);

        $this->getJson('/api/v1/masters/products?q='.urlencode('タマ ガワ'))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.products.0.id', $productId);

        $this->getJson('/api/v1/masters/products?capacity='.urlencode(' ７ ２ ０ '))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.products.0.id', $productId);
    }

    public function test_product_detail_is_managed_per_product_and_hides_legacy_fields(): void
    {
        [$unit, $tax] = $this->signInAsAdmin();
        $productId = $this->postJson('/api/v1/masters/products', $this->productPayload($unit, $tax))
            ->assertCreated()->json('data.product.id');

        $payload = $this->productPayload($unit, $tax);
        $payload['name'] = '玉川 カップ 改定';
        $payload['display_name'] = '玉川 カップ 改定 720ml';
        $payload['change_reason'] = '正式名称の訂正';

        $this->putJson("/api/v1/masters/products/{$productId}", $payload)
            ->assertOk()
            ->assertJsonPath('data.product.name', '玉川 カップ 改定')
            ->assertJsonPath('data.product.history.0.reason', '正式名称の訂正')
            ->assertJsonMissingPath('data.product.legacy_code')
            ->assertJsonMissingPath('data.product.legacy_name');
    }

    public function test_price_revision_closes_the_old_price_and_keeps_history(): void
    {
        [$unit, $tax] = $this->signInAsAdmin();
        $productId = $this->postJson('/api/v1/masters/products', $this->productPayload($unit, $tax))
            ->assertCreated()->json('data.product.id');

        $this->postJson("/api/v1/masters/products/{$productId}/price-revisions", [
            'price_type' => 'wholesale', 'unit_id' => $unit->id, 'unit_price' => '1000',
            'effective_from' => '2026-01-01', 'reason' => '初期価格',
        ])->assertCreated();

        $this->postJson("/api/v1/masters/products/{$productId}/price-revisions", [
            'price_type' => 'wholesale', 'unit_id' => $unit->id, 'unit_price' => '1100',
            'effective_from' => '2026-08-10', 'reason' => '原価上昇による改定',
        ])->assertCreated()
            ->assertJsonPath('data.price_rule.price_type', 'wholesale')
            ->assertJsonPath('data.price_rule.unit_price', '1100.0000')
            ->assertJsonPath('data.price_rule.effective_from', '2026-08-10');

        $this->postJson("/api/v1/masters/products/{$productId}/price-revisions", [
            'price_type' => 'retail', 'unit_id' => $unit->id, 'unit_price' => '2500',
            'effective_from' => '2026-01-01', 'reason' => '小売価格登録',
        ])->assertCreated();

        $customer = Customer::query()->create([
            'customer_code' => 'PRODUCT-PRICE-CUST-001',
            'name' => '商品価格確認先',
            'transaction_category_id' => TransactionCategory::query()->where('code', 'wholesale')->firstOrFail()->id,
            'settlement_receivable_category_id' => SettlementReceivableCategory::query()->where('code', 'accounts_receivable_1')->firstOrFail()->id,
            'billing_cycle_id' => BillingCycle::query()->where('code', 'monthly_end_next_month_end')->firstOrFail()->id,
        ]);

        PriceRule::query()->create([
            'price_list_id' => PriceList::query()->where('code', 'customer_price')->firstOrFail()->id,
            'product_id' => $productId,
            'customer_id' => $customer->id,
            'transaction_category_id' => null,
            'unit_id' => $unit->id,
            'unit_price' => '900',
            'currency' => 'JPY',
            'priority' => 100,
            'effective_from' => '2026-01-01',
            'rounding_method' => 'round',
            'reason' => '受注時に記憶',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('price_rules', [
            'product_id' => $productId, 'unit_price' => '1000.0000', 'effective_to' => '2026-08-09',
        ]);
        $this->assertDatabaseHas('price_rules', [
            'product_id' => $productId, 'unit_price' => '1100.0000', 'effective_from' => '2026-08-10', 'is_active' => true,
        ]);

        $this->getJson("/api/v1/masters/products/{$productId}")
            ->assertOk()
            ->assertJsonCount(3, 'data.product.price_rules')
            ->assertJsonPath('data.product.price_rules.0.price_type', 'wholesale')
            ->assertJsonFragment(['price_type' => 'retail', 'tax_included_unit_price' => '2750.0000'])
            ->assertJsonMissing(['unit_price' => '900.0000']);

        $this->postJson("/api/v1/masters/products/{$productId}/price-revisions", [
            'scope' => 'customer', 'customer_id' => $customer->id,
            'unit_id' => $unit->id, 'unit_price' => '800',
            'effective_from' => '2026-09-01', 'reason' => '商品マスタでは登録不可',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('price_type');

        $this->assertSame(4, PriceRule::query()->where('product_id', $productId)->count());
    }

    /** @return array{0: Unit, 1: ConsumptionTaxCategory} */
    private function signInAsAdmin(): array
    {
        $this->seed([
            FoundationPermissionSeeder::class, ProductUnitMasterSeeder::class, TaxMasterSeeder::class,
            CustomerMasterSeeder::class, PriceMasterSeeder::class,
        ]);
        $user = User::query()->create([
            'name' => '商品管理者', 'email' => 'product-item-master@example.test',
            'password' => 'password', 'is_active' => true,
        ]);
        $user->roles()->attach(Role::query()->where('code', 'admin')->firstOrFail());
        $this->actingAs($user);

        return [
            Unit::query()->where('code', 'bottle')->firstOrFail(),
            ConsumptionTaxCategory::query()->where('code', 'taxable_standard')->firstOrFail(),
        ];
    }

    /** @return array<string, mixed> */
    private function productPayload(Unit $unit, ConsumptionTaxCategory $tax): array
    {
        $milliliter = Unit::query()->where('code', 'milliliter')->firstOrFail();

        return [
            'product_code' => 'ＣＵＰ００１', 'product_type' => 'sake',
            'name' => '玉川　カップ', 'name_kana' => 'ﾀﾏｶﾞﾜ ｶｯﾌﾟ', 'display_name' => '玉川　カップ 720ml',
            'category_name' => '清酒', 'consumption_tax_category_id' => $tax->id,
            'base_unit_id' => $unit->id, 'sales_unit_id' => $unit->id, 'inventory_unit_id' => $unit->id,
            'capacity_value' => 720, 'capacity_unit_id' => $milliliter->id,
            'alcohol_percentage' => 15.5, 'liquor_tax_category_code' => 'seishu',
            'liquor_type_name' => '清酒', 'ingredients' => '米、米こうじ', 'rice_polishing_ratio' => 60,
            'production_method' => null, 'is_unpasteurized' => false,
            'kasu_type' => null, 'food_category' => null, 'allergen_note' => null,
            'storage_method' => null, 'shelf_life_days' => null, 'goods_category' => null,
            'material' => null, 'size_description' => null,
            'is_sales_available' => true, 'is_inventory_managed' => true, 'is_active' => true,
            'note' => null, 'change_reason' => null,
        ];
    }
}
