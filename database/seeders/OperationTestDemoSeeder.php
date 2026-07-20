<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class OperationTestDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DatabaseSeeder::class,
            DemoCustomerSeeder::class,
            DemoProductSeeder::class,
            OperationTestDemoUserSeeder::class,
            DemoTransactionSeeder::class,
            DemoEditableSalesOrderSeeder::class,
            DemoEditableBillingSeeder::class,
        ]);
    }
}
