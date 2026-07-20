<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class FoundationPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect(config('permissions.permissions', []))
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

