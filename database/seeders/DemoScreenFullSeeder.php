<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoScreenFullSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            FoundationPermissionSeeder::class,
            DemoReturnScreenSeeder::class,
            OperationTestDemoUserSeeder::class,
        ]);
    }
}
