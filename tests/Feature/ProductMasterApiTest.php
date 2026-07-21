<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ConsumptionTaxCategory;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\FoundationPermissionSeeder;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\TaxMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductMasterApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_master_is_available_as_a_direct_management_menu_item(): void
    {
        $this->signInAsAdmin();

        $this->get('/masters/products')
            ->assertOk()
            ->assertSee('商品マスター')
            ->assertSee('href="/masters/products" class="active">商品マスタ</a>', false);
    }

    public function test_admin_can_register_one_product_family_with_multiple_capacities(): void
    {
        $this->signInAsAdmin();
        $milliliter = Unit::query()->where('code', 'milliliter')->firstOrFail();
        $standardTax = ConsumptionTaxCategory::query()->where('code', 'taxable_standard')->firstOrFail();

        $response = $this->postJson('/api/v1/masters/product-families', [
            'name' => '安芸虎 山田錦80％ 純米',
            'name_kana' => 'アキトラ ヤマダニシキ ジュンマイ',
            'product_type' => 'sake',
            'brand_name' => '安芸虎',
            'category_name' => '純米酒',
            'consumption_tax_category_id' => $standardTax->id,
            'alcohol_percentage' => 15.5,
            'liquor_tax_category_code' => 'seishu',
            'liquor_type_name' => '清酒',
            'ingredients' => '米、米こうじ',
            'rice_polishing_ratio' => 80,
            'production_method' => null,
            'is_unpasteurized' => false,
            'is_sales_available' => true,
            'is_inventory_managed' => true,
            'is_active' => true,
            'note' => null,
            'variants' => [
                ['capacity_value' => 720, 'capacity_unit_id' => $milliliter->id],
                ['capacity_value' => 1800, 'capacity_unit_id' => $milliliter->id],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.product_family.name', '安芸虎 山田錦80％ 純米')
            ->assertJsonPath('data.product_family.products_count', 2);

        $familyId = $response->json('data.product_family.id');
        $family = ProductFamily::query()->findOrFail($familyId);
        $this->assertFalse($family->is_unpasteurized);
        $this->assertSame([
            '安芸虎 山田錦80％ 純米 720ml',
            '安芸虎 山田錦80％ 純米 1800ml',
        ], $family->products()->orderBy('capacity_value')->pluck('display_name')->all());

        $this->getJson('/api/v1/masters/product-families?q=1800ml')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.product_families.0.id', $familyId);
        $this->getJson('/api/v1/masters/product-families?missing=unpasteurized_review')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);
        $this->getJson("/api/v1/masters/product-families/{$familyId}")
            ->assertOk()
            ->assertJsonPath('data.product_family.products.0.is_package_locked', false);

        $export = $this->get('/api/v1/masters/product-families/export?q=山田錦')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('安芸虎 山田錦80％ 純米 720ml', $export->streamedContent());
    }

    public function test_family_update_refreshes_product_names_but_cannot_change_product_type(): void
    {
        $this->signInAsAdmin();
        $unit = Unit::query()->where('code', 'milliliter')->firstOrFail();
        $tax = ConsumptionTaxCategory::query()->where('code', 'taxable_standard')->firstOrFail();
        $family = ProductFamily::query()->create($this->familyAttributes($tax->id));
        $product = Product::query()->create($this->productAttributes($family->id, $unit->id, $tax->id));

        $payload = $this->familyPayload($tax->id);
        $payload['name'] = '安芸虎 純米吟醸 生酒';
        $payload['is_unpasteurized'] = true;
        $payload['change_reason'] = '生酒の商品群として名称を補正';
        $this->putJson("/api/v1/masters/product-families/{$family->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.product_family.name', '安芸虎 純米吟醸 生酒');

        $this->assertSame('安芸虎 純米吟醸 生酒 720ml', $product->refresh()->display_name);
        $this->assertDatabaseHas('sake_product_details', ['product_id' => $product->id, 'is_unpasteurized' => true]);
        $this->assertSame(1, AuditLog::query()->where('target_table', 'product_families')->where('target_id', (string) $family->id)->count());

        $payload['product_type'] = 'goods';
        $payload['alcohol_percentage'] = null;
        $payload['liquor_tax_category_code'] = null;
        $this->putJson("/api/v1/masters/product-families/{$family->id}", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_type');
    }

    public function test_admin_can_add_and_disable_a_product_variant(): void
    {
        $this->signInAsAdmin();
        $unit = Unit::query()->where('code', 'milliliter')->firstOrFail();
        $tax = ConsumptionTaxCategory::query()->where('code', 'taxable_standard')->firstOrFail();
        $family = ProductFamily::query()->create($this->familyAttributes($tax->id));

        $response = $this->postJson("/api/v1/masters/product-families/{$family->id}/products", [
            'product_code' => 'TEST-P-1800',
            'capacity_value' => 1800,
            'capacity_unit_id' => $unit->id,
            'variant_label' => null,
            'is_active' => true,
            'change_reason' => '一升瓶規格を追加',
        ])->assertCreated()
            ->assertJsonPath('data.product.product_code', 'TEST-P-1800')
            ->assertJsonPath('data.product.display_name', '安芸虎 純米吟醸 1800ml')
            ->assertJsonPath('data.product.is_active', true);

        $productId = $response->json('data.product.id');
        $this->putJson("/api/v1/masters/product-families/{$family->id}/products/{$productId}", [
            'capacity_value' => 1800,
            'capacity_unit_id' => $unit->id,
            'variant_label' => '一升瓶',
            'is_active' => false,
            'change_reason' => '一升瓶規格を休止',
        ])->assertOk()
            ->assertJsonPath('data.product.display_name', '安芸虎 純米吟醸 一升瓶')
            ->assertJsonPath('data.product.is_active', false);

        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'variant_label' => '一升瓶',
            'is_active' => false,
        ]);
        $this->assertNotNull(Product::query()->findOrFail($productId)->disabled_at);
        $this->assertSame(2, AuditLog::query()->where('target_table', 'products')->where('target_id', (string) $productId)->count());
    }

    public function test_capacity_cannot_be_changed_after_the_product_has_price_history(): void
    {
        $this->signInAsAdmin();
        $unit = Unit::query()->where('code', 'milliliter')->firstOrFail();
        $bottle = Unit::query()->where('code', 'bottle')->firstOrFail();
        $tax = ConsumptionTaxCategory::query()->where('code', 'taxable_standard')->firstOrFail();
        $family = ProductFamily::query()->create($this->familyAttributes($tax->id));
        $product = Product::query()->create($this->productAttributes($family->id, $unit->id, $tax->id));
        $priceListId = DB::table('price_lists')->insertGetId([
            'code' => 'TEST-PRICE', 'name' => 'テスト価格表', 'price_type' => 'standard',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('price_rules')->insert([
            'price_list_id' => $priceListId, 'product_id' => $product->id, 'unit_id' => $bottle->id,
            'unit_price' => 1500, 'currency' => 'JPY', 'priority' => 1000,
            'effective_from' => today(), 'rounding_method' => 'round', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->putJson("/api/v1/masters/product-families/{$family->id}/products/{$product->id}", [
            'capacity_value' => 900,
            'capacity_unit_id' => $unit->id,
            'variant_label' => '900ml',
            'is_active' => true,
            'change_reason' => '容量訂正',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('capacity_value');

        $this->assertSame('720.0000', $product->refresh()->capacity_value);
        $this->assertSame('安芸虎 純米吟醸 720ml', $product->display_name);
    }

    public function test_product_family_filters_distinguish_type_status_and_missing_fields(): void
    {
        $this->signInAsAdmin();
        $unit = Unit::query()->where('code', 'piece')->firstOrFail();
        $tax = ConsumptionTaxCategory::query()->where('code', 'taxable_standard')->firstOrFail();
        $goods = ProductFamily::query()->create([
            ...$this->familyAttributes($tax->id),
            'family_code' => 'TEST-F-GOODS', 'product_type' => 'goods', 'name' => '休止グッズ',
            'alcohol_percentage' => null, 'is_alcohol' => false, 'liquor_tax_category_code' => null,
            'liquor_type_name' => null, 'is_active' => false, 'disabled_at' => now(),
        ]);
        Product::query()->create([
            ...$this->productAttributes($goods->id, $unit->id, $tax->id),
            'product_code' => 'TEST-GOODS-001', 'product_type' => 'goods', 'name' => '休止グッズ',
            'display_name' => '休止グッズ', 'variant_label' => '通常品',
            'capacity_value' => null, 'alcohol_percentage' => null, 'is_alcohol' => false,
            'is_active' => false, 'disabled_at' => now(),
        ]);

        $this->getJson('/api/v1/masters/product-families?product_type=goods&active=inactive')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.product_families.0.id', $goods->id);
        $this->getJson('/api/v1/masters/product-families?missing=capacity&active=all')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.product_families.0.id', $goods->id);
        $this->getJson('/api/v1/masters/product-families?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_product_master_requires_permission(): void
    {
        $this->seed(FoundationPermissionSeeder::class);
        $user = User::query()->create([
            'name' => '権限なし', 'email' => 'no-product-master@example.test',
            'password' => 'password', 'is_active' => true,
        ]);

        $this->actingAs($user)->get('/masters/products')->assertForbidden();
        $this->actingAs($user)->getJson('/api/v1/masters/product-families')->assertForbidden();
        $this->actingAs($user)->postJson('/api/v1/masters/product-families', [])->assertForbidden();
    }

    private function signInAsAdmin(): void
    {
        $this->seed([FoundationPermissionSeeder::class, ProductUnitMasterSeeder::class, TaxMasterSeeder::class]);
        $user = User::query()->create([
            'name' => '商品マスター管理者', 'email' => 'product-master@example.test',
            'password' => 'password', 'is_active' => true,
        ]);
        $user->roles()->attach(Role::query()->where('code', 'admin')->firstOrFail());
        $this->actingAs($user);
    }

    private function familyAttributes(int $taxCategoryId): array
    {
        return [
            'family_code' => 'TEST-F-001', 'product_type' => 'sake', 'name' => '安芸虎 純米吟醸',
            'consumption_tax_category_id' => $taxCategoryId, 'alcohol_percentage' => 15.5,
            'is_alcohol' => true, 'liquor_tax_category_code' => 'seishu', 'liquor_type_name' => '清酒',
            'is_unpasteurized' => false, 'is_sales_available' => true, 'is_inventory_managed' => true,
            'is_active' => true,
        ];
    }

    private function productAttributes(int $familyId, int $unitId, int $taxCategoryId): array
    {
        $bottle = Unit::query()->where('code', 'bottle')->firstOrFail();

        return [
            'product_family_id' => $familyId, 'product_code' => 'TEST-P-001', 'product_type' => 'sake',
            'name' => '安芸虎 純米吟醸', 'display_name' => '安芸虎 純米吟醸 720ml', 'variant_label' => '720ml',
            'base_unit_id' => $bottle->id, 'sales_unit_id' => $bottle->id, 'inventory_unit_id' => $bottle->id,
            'capacity_value' => 720, 'capacity_unit_id' => $unitId, 'consumption_tax_category_id' => $taxCategoryId,
            'alcohol_percentage' => 15.5, 'is_alcohol' => true, 'is_sales_available' => true,
            'is_inventory_managed' => true, 'is_active' => true,
        ];
    }

    private function familyPayload(int $taxCategoryId): array
    {
        return [
            'name' => '安芸虎 純米吟醸', 'name_kana' => null, 'product_type' => 'sake',
            'brand_name' => '安芸虎', 'category_name' => '純米吟醸',
            'consumption_tax_category_id' => $taxCategoryId, 'alcohol_percentage' => 15.5,
            'liquor_tax_category_code' => 'seishu', 'liquor_type_name' => '清酒',
            'ingredients' => null, 'rice_polishing_ratio' => null, 'production_method' => null,
            'is_unpasteurized' => false, 'is_sales_available' => true,
            'is_inventory_managed' => true, 'is_active' => true, 'note' => null,
        ];
    }
}
