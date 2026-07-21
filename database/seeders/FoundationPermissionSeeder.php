<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class FoundationPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $foundationMasterPermissions = [
            'tax_master.view' => '消費税マスタ閲覧',
            'tax_master.edit' => '消費税マスタ編集',
            'unit_master.view' => '単位マスタ閲覧',
            'unit_master.edit' => '単位マスタ編集',
            'liquor_tax_master.view' => '酒税区分マスタ閲覧',
            'liquor_tax_master.edit' => '酒税区分マスタ編集',
            'stock_location_master.view' => '在庫場所マスタ閲覧',
            'stock_location_master.edit' => '在庫場所マスタ編集',
            'transaction_category_master.view' => '取引区分マスタ閲覧',
            'transaction_category_master.edit' => '取引区分マスタ編集',
            'settlement_category_master.view' => '売掛精算区分マスタ閲覧',
            'settlement_category_master.edit' => '売掛精算区分マスタ編集',
            'number_sequence_master.view' => '採番マスタ閲覧',
            'number_sequence_master.edit' => '採番マスタ編集',
            'role_master.view' => '権限・ロール閲覧',
            'role_master.edit' => '権限・ロール編集',
        ];

        $permissions = collect(array_merge(config('permissions.permissions', []), $foundationMasterPermissions))
            ->map(function (string $name, string $code): Permission {
                return Permission::updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => $name,
                        'description' => null,
                        'is_system' => true,
                        'is_active' => true,
                    ],
                );
            });

        $adminRoleConfig = config('permissions.roles.admin');

        $adminRole = Role::updateOrCreate(
            ['code' => 'admin'],
            [
                'name' => $adminRoleConfig['name'],
                'description' => $adminRoleConfig['description'],
                'is_system' => true,
                'is_active' => true,
            ],
        );

        $adminRole->permissions()->sync($permissions->pluck('id')->all());
    }
}

