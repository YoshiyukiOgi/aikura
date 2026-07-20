<?php

namespace Database\Seeders;

use App\Models\NumberSequence;
use Illuminate\Database\Seeder;

class ShipmentMasterSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(TaxMasterSeeder::class);
        $this->call(LiquorTaxMasterSeeder::class);
        $this->call(StockLocationSeeder::class);

        NumberSequence::updateOrCreate(
            ['code' => 'shipment_document'],
            [
                'name' => '出荷伝票番号',
                'prefix' => 'S-{YYYY}{MM}-',
                'suffix' => null,
                'current_number' => 0,
                'padding_length' => 6,
                'reset_type' => 'month',
                'last_reset_on' => null,
                'description' => '出荷伝票ヘッダ作成時に使用する採番。',
                'is_active' => true,
            ],
        );

        NumberSequence::updateOrCreate(
            ['code' => 'invoice_document'],
            [
                'name' => '請求番号',
                'prefix' => 'I-{YYYY}{MM}-',
                'suffix' => null,
                'current_number' => 0,
                'padding_length' => 6,
                'reset_type' => 'month',
                'last_reset_on' => null,
                'description' => '請求ヘッダ作成時に使用する採番。',
                'is_active' => true,
            ],
        );

        NumberSequence::updateOrCreate(
            ['code' => 'sales_return'],
            [
                'name' => '返品番号',
                'prefix' => 'R-{YYYY}{MM}-',
                'suffix' => null,
                'current_number' => 0,
                'padding_length' => 6,
                'reset_type' => 'month',
                'last_reset_on' => null,
                'description' => '返品・赤伝処理作成時に使用する採番。',
                'is_active' => true,
            ],
        );

        NumberSequence::updateOrCreate(
            ['code' => 'credit_memo'],
            [
                'name' => '赤伝番号',
                'prefix' => 'C-{YYYY}{MM}-',
                'suffix' => null,
                'current_number' => 0,
                'padding_length' => 6,
                'reset_type' => 'month',
                'last_reset_on' => null,
                'description' => '返品に紐づく赤伝ドラフト作成時に使用する採番。',
                'is_active' => true,
            ],
        );

        NumberSequence::updateOrCreate(
            ['code' => 'non_sales_stock_operation'],
            [
                'name' => '販売外在庫出入番号',
                'prefix' => 'NS-{YYYY}{MM}-',
                'suffix' => null,
                'current_number' => 0,
                'padding_length' => 6,
                'reset_type' => 'month',
                'last_reset_on' => null,
                'description' => '戻入、瓶詰、破損、詰替、廃棄など販売外在庫出入に使用する採番。',
                'is_active' => true,
            ],
        );
        NumberSequence::updateOrCreate(
            ['code' => 'sales_order'],
            [
                'name' => 'Sales order number',
                'prefix' => 'O-{YYYY}{MM}-',
                'suffix' => null,
                'current_number' => 0,
                'padding_length' => 6,
                'reset_type' => 'month',
                'last_reset_on' => null,
                'description' => 'Used when creating sales orders.',
                'is_active' => true,
            ],
        );

        NumberSequence::updateOrCreate(
            ['code' => 'shipment_instruction'],
            [
                'name' => 'Shipment instruction number',
                'prefix' => 'SI-{YYYY}{MM}-',
                'suffix' => null,
                'current_number' => 0,
                'padding_length' => 6,
                'reset_type' => 'month',
                'last_reset_on' => null,
                'description' => 'Used when creating shipment instructions.',
                'is_active' => true,
            ],
        );

        NumberSequence::updateOrCreate(
            ['code' => 'shipment_pick'],
            [
                'name' => 'Shipment pick number',
                'prefix' => 'P-{YYYY}{MM}-',
                'suffix' => null,
                'current_number' => 0,
                'padding_length' => 6,
                'reset_type' => 'month',
                'last_reset_on' => null,
                'description' => 'Used when creating shipment picks.',
                'is_active' => true,
            ],
        );
    }
}
