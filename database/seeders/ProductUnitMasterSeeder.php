<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class ProductUnitMasterSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'bottle', 'name' => '本', 'symbol' => '本', 'unit_type' => 'count', 'decimal_scale' => 0],
            ['code' => 'case', 'name' => 'ケース', 'symbol' => 'CS', 'unit_type' => 'count', 'decimal_scale' => 0],
            ['code' => 'box', 'name' => '箱', 'symbol' => '箱', 'unit_type' => 'count', 'decimal_scale' => 0],
            ['code' => 'liter', 'name' => 'リットル', 'symbol' => 'L', 'unit_type' => 'volume', 'decimal_scale' => 3],
            ['code' => 'milliliter', 'name' => 'ミリリットル', 'symbol' => 'ml', 'unit_type' => 'volume', 'decimal_scale' => 0],
            ['code' => 'kilogram', 'name' => 'キログラム', 'symbol' => 'kg', 'unit_type' => 'weight', 'decimal_scale' => 3],
            ['code' => 'gram', 'name' => 'グラム', 'symbol' => 'g', 'unit_type' => 'weight', 'decimal_scale' => 0],
            ['code' => 'bag', 'name' => '袋', 'symbol' => '袋', 'unit_type' => 'count', 'decimal_scale' => 0],
            ['code' => 'piece', 'name' => '個', 'symbol' => '個', 'unit_type' => 'count', 'decimal_scale' => 0],
        ] as $unit) {
            Unit::updateOrCreate(
                ['code' => $unit['code']],
                [
                    'name' => $unit['name'],
                    'symbol' => $unit['symbol'],
                    'unit_type' => $unit['unit_type'],
                    'decimal_scale' => $unit['decimal_scale'],
                    'is_active' => true,
                ],
            );
        }
    }
}

