<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            FoundationPermissionSeeder::class,
            CustomerMasterSeeder::class,
            ProductUnitMasterSeeder::class,
            PriceMasterSeeder::class,
            TaxMasterSeeder::class,
            LiquorTaxMasterSeeder::class,
            StockLocationSeeder::class,
            ShipmentMasterSeeder::class,
        ]);
    }
}
