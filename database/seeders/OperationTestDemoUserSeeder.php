<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OperationTestDemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::query()->where('code', 'admin')->firstOrFail();

        foreach ([
            ['name' => 'デモ業務担当者', 'email' => 'demo.operator@example.test'],
            ['name' => 'デモ承認者', 'email' => 'demo.approver@example.test'],
        ] as $attributes) {
            $user = User::updateOrCreate(
                ['email' => $attributes['email']],
                [
                    'name' => $attributes['name'],
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'disabled_at' => null,
                ],
            );

            $user->roles()->syncWithoutDetaching([$adminRole->id]);
        }
    }
}
