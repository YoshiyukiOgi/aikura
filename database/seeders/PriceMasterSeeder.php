<?php

namespace Database\Seeders;

use App\Models\PriceList;
use Illuminate\Database\Seeder;

class PriceMasterSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->priceLists() as $priceList) {
            PriceList::updateOrCreate(
                ['code' => $priceList['code']],
                [
                    'name' => $priceList['name'],
                    'price_type' => $priceList['price_type'],
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @return array<int, array{code: string, name: string, price_type: string}>
     */
    private function priceLists(): array
    {
        return [
            ['code' => 'standard', 'name' => '商品標準価格', 'price_type' => 'standard'],
            ['code' => 'common', 'name' => '共通価格表', 'price_type' => 'common'],
            ['code' => 'transaction_category', 'name' => '取引区分価格表', 'price_type' => 'transaction_category'],
            ['code' => 'customer', 'name' => '取引先個別価格表', 'price_type' => 'customer'],
            ['code' => 'producer_price', 'name' => '生産者価格表', 'price_type' => 'producer'],
            ['code' => 'wholesale_price', 'name' => '卸価格表', 'price_type' => 'wholesale'],
            ['code' => 'retail_price', 'name' => '小売価格表', 'price_type' => 'retail'],
            ['code' => 'customer_price', 'name' => '取引先個別価格表', 'price_type' => 'customer'],
        ];
    }
}
