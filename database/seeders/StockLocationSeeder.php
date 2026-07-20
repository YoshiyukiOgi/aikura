<?php

namespace Database\Seeders;

use App\Models\StockLocation;
use Illuminate\Database\Seeder;

class StockLocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            [
                'code' => 'main_brewery',
                'name' => '本社蔵元',
                'location_type' => 'brewery',
                'is_default_shipping_location' => true,
                'is_default_receiving_location' => true,
                'is_inventory_managed' => true,
                'is_shippable' => true,
                'is_sellable' => true,
                'is_tax_relevant' => true,
                'sort_order' => 10,
            ],
            [
                'code' => 'cold_storage',
                'name' => '冷蔵倉庫',
                'location_type' => 'warehouse',
                'is_inventory_managed' => true,
                'is_shippable' => true,
                'is_sellable' => true,
                'is_tax_relevant' => true,
                'sort_order' => 20,
            ],
            [
                'code' => 'shipping_area',
                'name' => '出荷エリア',
                'location_type' => 'shipping',
                'is_inventory_managed' => true,
                'is_shippable' => true,
                'is_sellable' => true,
                'is_tax_relevant' => true,
                'sort_order' => 30,
            ],
            [
                'code' => 'storefront',
                'name' => '直売所',
                'location_type' => 'store',
                'is_inventory_managed' => true,
                'is_shippable' => true,
                'is_sellable' => true,
                'is_tax_relevant' => true,
                'sort_order' => 40,
            ],
            [
                'code' => 'consignment',
                'name' => '委託在庫',
                'location_type' => 'consignment',
                'is_inventory_managed' => true,
                'is_shippable' => false,
                'is_sellable' => true,
                'is_tax_relevant' => true,
                'sort_order' => 50,
            ],
            [
                'code' => 'inspection_hold',
                'name' => '検品保留',
                'location_type' => 'hold',
                'is_inventory_managed' => true,
                'is_shippable' => false,
                'is_sellable' => false,
                'is_tax_relevant' => true,
                'sort_order' => 60,
            ],
            [
                'code' => 'disposal_waiting',
                'name' => '廃棄待ち',
                'location_type' => 'disposal',
                'is_inventory_managed' => true,
                'is_shippable' => false,
                'is_sellable' => false,
                'is_tax_relevant' => true,
                'sort_order' => 70,
            ],
            [
                'code' => 'external',
                'name' => '外部倉庫',
                'location_type' => 'external',
                'is_inventory_managed' => false,
                'is_shippable' => false,
                'is_sellable' => false,
                'is_tax_relevant' => false,
                'sort_order' => 90,
            ],
        ];

        foreach ($locations as $location) {
            StockLocation::updateOrCreate(
                ['code' => $location['code']],
                $location + ['is_active' => true],
            );
        }
    }
}
