<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ConsumptionTaxCategory;
use App\Models\Permission;
use App\Models\ProductionLot;
use App\Models\Role;
use App\Models\StockLocation;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\FoundationPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationMasterApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_master_screen_and_manage_units(): void
    {
        $this->actingAsAdmin();

        $this->get('/masters/foundation/units')
            ->assertOk()
            ->assertSee('単位マスタ')
            ->assertSee('マスタ');

        $created = $this->postJson('/api/v1/masters/foundation/units', [
            'code' => 'test-bottle',
            'name' => 'テスト本',
            'symbol' => '本',
            'unit_type' => 'count',
            'decimal_scale' => 0,
            'description' => 'テスト用単位',
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.record.code', 'test-bottle');

        $id = $created->json('data.record.id');

        $this->putJson("/api/v1/masters/foundation/units/{$id}", [
            'code' => 'test-bottle',
            'name' => 'テスト本改',
            'symbol' => '本',
            'unit_type' => 'count',
            'decimal_scale' => 0,
            'description' => '名称変更',
            'is_active' => true,
            'change_reason' => '単位名称の見直し',
        ])->assertOk()
            ->assertJsonPath('data.record.name', 'テスト本改');

        $this->assertDatabaseHas('units', ['code' => 'test-bottle', 'name' => 'テスト本改']);
        $this->assertSame(2, AuditLog::query()->where('target_table', 'units')->where('target_id', (string) $id)->count());
    }

    public function test_admin_can_manage_consumption_tax_category_and_rate(): void
    {
        $this->actingAsAdmin();

        $categoryId = $this->postJson('/api/v1/masters/foundation/consumption-tax-categories', [
            'code' => 'test-standard',
            'name' => 'テスト標準課税',
            'taxability' => 'taxable',
            'requires_tax_rate' => true,
            'is_reduced_rate' => false,
            'is_export_exempt' => false,
            'is_invoice_display_target' => true,
            'sort_order' => 10,
            'description' => null,
            'is_active' => true,
        ])->assertCreated()
            ->json('data.record.id');

        $this->postJson('/api/v1/masters/foundation/consumption-tax-rates', [
            'consumption_tax_category_id' => $categoryId,
            'name' => '10%',
            'rate' => 10,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'description' => 'テスト税率',
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.record.rate', '10.0000');

        $this->getJson('/api/v1/masters/foundation/consumption-tax-rates?q=10')
            ->assertOk()
            ->assertJsonPath('data.records.0.consumption_tax_category_name', 'テスト標準課税');
    }

    public function test_admin_can_update_role_permissions(): void
    {
        $this->actingAsAdmin();

        $permission = Permission::query()->where('code', 'unit_master.view')->firstOrFail();

        $created = $this->postJson('/api/v1/masters/foundation/roles', [
            'code' => 'unit-viewer',
            'name' => '単位閲覧担当',
            'description' => '単位マスタのみ閲覧',
            'is_system' => false,
            'permission_ids' => [$permission->id],
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.record.permissions_count', 1);

        $role = Role::query()->where('code', 'unit-viewer')->firstOrFail();
        $this->assertTrue($role->permissions()->whereKey($permission->id)->exists());

        $this->putJson('/api/v1/masters/foundation/roles/'.$created->json('data.record.id'), [
            'code' => 'unit-viewer',
            'name' => '単位閲覧担当',
            'description' => '権限を外す',
            'is_system' => false,
            'permission_ids' => [],
            'is_active' => true,
            'change_reason' => '権限整理',
        ])->assertOk()
            ->assertJsonPath('data.record.permissions_count', 0);
    }

    public function test_admin_can_manage_lots_in_master(): void
    {
        $this->actingAsAdmin();

        $location = StockLocation::query()->create([
            'code' => 'lot-master-main',
            'name' => 'ロット倉庫',
            'location_type' => 'warehouse',
            'is_inventory_managed' => true,
            'is_active' => true,
        ]);
        $unit = Unit::query()->create([
            'code' => 'lot-master-bottle',
            'name' => 'ロット本',
            'symbol' => '本',
            'unit_type' => 'count',
            'decimal_scale' => 0,
            'is_active' => true,
        ]);
        $capacityUnit = Unit::query()->create([
            'code' => 'lot-master-ml',
            'name' => 'ミリリットル',
            'symbol' => 'ml',
            'unit_type' => 'volume',
            'decimal_scale' => 0,
            'is_active' => true,
        ]);

        $this->get('/masters/foundation/lots')
            ->assertOk()
            ->assertSee('ロットマスタ')
            ->assertSee('マスタ');

        $created = $this->postJson('/api/v1/masters/foundation/lots', [
            'lot_code' => 'LOT-MASTER-001',
            'display_name' => 'ロットマスタ登録',
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'capacity_value' => 720,
            'capacity_unit_id' => $capacityUnit->id,
            'alcohol_percentage' => 15.5,
            'analysis_status' => 'confirmed',
            'production_date' => '2026-08-01',
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.record.lot_code', 'LOT-MASTER-001');

        $lotId = $created->json('data.record.id');

        $this->putJson("/api/v1/masters/foundation/lots/{$lotId}", [
            'lot_code' => 'LOT-MASTER-001',
            'display_name' => 'ロットマスタ更新',
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'capacity_value' => 1800,
            'capacity_unit_id' => $capacityUnit->id,
            'alcohol_percentage' => 16.0,
            'analysis_status' => 'provisional',
            'production_date' => '2026-08-02',
            'is_active' => true,
            'change_reason' => 'ロット情報の補正',
        ])->assertOk()
            ->assertJsonPath('data.record.display_name', 'ロットマスタ更新')
            ->assertJsonPath('data.record.capacity_value', '1800.0000');

        $this->assertDatabaseHas('production_lots', [
            'id' => $lotId,
            'lot_code' => 'LOT-MASTER-001',
            'display_name' => 'ロットマスタ更新',
        ]);
        $this->assertSame(2, AuditLog::query()->where('target_table', 'production_lots')->where('target_id', (string) $lotId)->count());
        $this->assertSame('ロットマスタ更新', ProductionLot::query()->findOrFail($lotId)->display_name);
    }

    public function test_user_without_master_permission_is_forbidden(): void
    {
        $this->seed(FoundationPermissionSeeder::class);
        $user = User::query()->create([
            'name' => 'No master permission',
            'email' => 'foundation-master-readonly@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get('/masters/foundation/units')->assertForbidden();
        $this->actingAs($user)->getJson('/api/v1/masters/foundation/units')->assertForbidden();
        $this->actingAs($user)->postJson('/api/v1/masters/foundation/units', [])->assertForbidden();
    }

    public function test_role_manage_permission_can_manage_foundation_masters_as_admin_fallback(): void
    {
        $this->seed(FoundationPermissionSeeder::class);
        $role = Role::query()->create([
            'code' => 'settings-admin',
            'name' => '設定管理者',
            'description' => null,
            'is_system' => false,
            'is_active' => true,
        ]);
        $role->permissions()->attach(Permission::query()->where('code', 'role.manage')->firstOrFail());
        $user = User::query()->create([
            'name' => 'Settings admin',
            'email' => 'settings-admin@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->roles()->attach($role);

        $this->actingAs($user)->get('/masters/foundation/units')
            ->assertOk()
            ->assertSee('単位マスタ');
        $this->actingAs($user)->getJson('/api/v1/masters/foundation/units')->assertOk();
    }

    private function actingAsAdmin(): User
    {
        $this->seed(FoundationPermissionSeeder::class);
        $user = User::query()->create([
            'name' => 'Foundation Master Administrator',
            'email' => 'foundation-master-admin@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::query()->where('code', 'admin')->firstOrFail());
        $this->actingAs($user);

        return $user;
    }
}
