<?php

namespace Database\Seeders;

use App\Models\ConsumptionTaxCategory;
use App\Models\FoodProductDetail;
use App\Models\GoodsProductDetail;
use App\Models\KasuProductDetail;
use App\Models\PriceList;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\SakeProductDetail;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\UnitConversion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DemoProductSeeder extends Seeder
{
    public function run(): void
    {
        $units = Unit::query()->whereIn('code', ['bottle', 'bag', 'piece', 'liter', 'kilogram', 'milliliter', 'gram'])->get()->keyBy('code');
        $taxes = ConsumptionTaxCategory::query()->whereIn('code', ['taxable_standard', 'taxable_reduced'])->get()->keyBy('code');
        $priceList = PriceList::query()->where('code', 'common')->firstOrFail();
        $location = StockLocation::query()->where('code', 'main_brewery')->firstOrFail();

        foreach ($this->products() as $index => $row) {
            $baseUnit = $units->get($row['unit']);
            $capacityUnit = $units->get($row['capacity_unit']);
            $taxCategory = $taxes->get($row['tax_category']);

            $product = Product::updateOrCreate(
                ['product_code' => $row['product_code']],
                [
                    'product_type' => $row['product_type'],
                    'name' => $row['name'],
                    'name_kana' => $row['name_kana'],
                    'display_name' => $row['name'],
                    'brand_name' => '架空酒造デモ',
                    'series_name' => $row['series_name'],
                    'style_name' => $row['style_name'],
                    'category_name' => $row['category_name'],
                    'consumption_tax_category_id' => $taxCategory->id,
                    'base_unit_id' => $baseUnit->id,
                    'sales_unit_id' => $baseUnit->id,
                    'inventory_unit_id' => $baseUnit->id,
                    'capacity_value' => $row['capacity_value'],
                    'capacity_unit_id' => $capacityUnit->id,
                    'alcohol_percentage' => $row['alcohol_percentage'] === '' ? null : $row['alcohol_percentage'],
                    'is_alcohol' => $row['product_type'] === 'sake',
                    'is_sales_available' => true,
                    'is_inventory_managed' => true,
                    'search_key' => strtolower($row['product_code']).' '.$row['name_kana'].' '.$row['category_name'].' '.$row['style_name'],
                    'legacy_code' => null,
                    'legacy_name' => null,
                    'note' => '架空のデモ商品。実在の商品、原材料、在庫、価格とは無関係。',
                    'is_active' => true,
                    'disabled_at' => null,
                ],
            );

            $this->createDetail($product, $row);
            $this->createUnitConversion($product, $row, $units);
            $this->createPriceRule($priceList, $product, $row);
            $this->createLotAndOpeningStock($location, $product, $row, $index + 1);
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    private function createDetail(Product $product, array $row): void
    {
        match ($row['product_type']) {
            'sake' => SakeProductDetail::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'liquor_tax_category_code' => 'seishu',
                    'liquor_type_name' => $row['liquor_type_name'],
                    'ingredients' => $row['ingredients'],
                    'rice_polishing_ratio' => $row['rice_polishing_ratio'],
                    'production_method' => $row['production_method'],
                    'is_unpasteurized' => $row['is_unpasteurized'] === 'true',
                ],
            ),
            'food' => FoodProductDetail::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'food_category' => $row['detail_category'],
                    'allergen_note' => null,
                    'storage_method' => $row['storage_method'],
                    'shelf_life_days' => (int) $row['shelf_life_days'],
                ],
            ),
            'kasu' => KasuProductDetail::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'kasu_type' => $row['detail_category'],
                    'storage_method' => $row['storage_method'],
                ],
            ),
            'goods' => GoodsProductDetail::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'goods_category' => $row['detail_category'],
                    'material' => $row['material'],
                    'size_description' => $row['size_description'],
                ],
            ),
        };
    }

    /**
     * @param  array<string, string>  $row
     * @param  Collection<string, Unit>  $units
     */
    private function createUnitConversion(Product $product, array $row, $units): void
    {
        if ($row['product_type'] !== 'goods' && $row['capacity_unit'] === 'milliliter') {
            UnitConversion::updateOrCreate(
                ['product_id' => $product->id, 'from_unit_id' => $product->base_unit_id, 'to_unit_id' => $units->get('liter')->id],
                ['factor' => bcdiv($row['capacity_value'], '1000', 6), 'rounding_method' => 'none', 'is_active' => true],
            );
        }

        if ($row['product_type'] !== 'goods' && $row['capacity_unit'] === 'gram') {
            UnitConversion::updateOrCreate(
                ['product_id' => $product->id, 'from_unit_id' => $product->base_unit_id, 'to_unit_id' => $units->get('kilogram')->id],
                ['factor' => bcdiv($row['capacity_value'], '1000', 6), 'rounding_method' => 'none', 'is_active' => true],
            );
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    private function createPriceRule(PriceList $priceList, Product $product, array $row): void
    {
        PriceRule::updateOrCreate(
            [
                'price_list_id' => $priceList->id,
                'product_id' => $product->id,
                'customer_id' => null,
                'transaction_category_id' => null,
                'unit_id' => $product->sales_unit_id,
                'effective_from' => '2026-06-01',
            ],
            [
                'unit_price' => $row['unit_price'],
                'currency' => 'JPY',
                'priority' => 100,
                'effective_to' => null,
                'rounding_method' => 'round',
                'reason' => '架空デモ商品用の共通価格',
                'is_active' => true,
            ],
        );
    }

    /**
     * @param  array<string, string>  $row
     */
    private function createLotAndOpeningStock(StockLocation $location, Product $product, array $row, int $lineNo): void
    {
        $lot = ProductionLot::updateOrCreate(
            ['lot_code' => "DEMO-LOT-{$row['product_code']}-202606"],
            [
                'display_name' => "{$row['name']} 2026年6月デモロット",
                'status' => 'active',
                'stock_location_id' => $location->id,
                'unit_id' => $product->inventory_unit_id,
                'capacity_value' => $product->capacity_value,
                'capacity_unit_id' => $product->capacity_unit_id,
                'alcohol_percentage' => $product->alcohol_percentage,
                'analysis_status' => $product->is_alcohol ? 'confirmed' : null,
                'production_date' => '2026-05-20',
                'bottling_date' => '2026-06-01',
                'best_before_date' => $row['product_type'] === 'sake' ? null : '2026-12-31',
                'tank_code' => $row['product_type'] === 'sake' ? 'DEMO-TANK-2' : null,
                'rice_variety' => $row['product_type'] === 'sake' ? 'デモ米' : null,
                'rice_polishing_ratio' => $row['product_type'] === 'sake' ? $row['rice_polishing_ratio'] : null,
                'production_method' => $row['product_type'] === 'sake' ? $row['production_method'] : null,
                'storage_condition' => $row['storage_method'],
                'external_system_code' => "DEMO-EXT-{$row['product_code']}",
                'legacy_lot_text' => "デモ旧ロット {$row['product_code']}",
                'search_key' => "demo {$row['product_code']} {$row['name_kana']}",
                'note' => '架空のデモロット。実在庫には使用しない。',
                'is_active' => true,
                'disabled_at' => null,
            ],
        );

        StockMovement::updateOrCreate(
            ['source_type' => 'demo_product_seeder', 'source_document_number' => 'DEMO-PRODUCT-OPENING-202606', 'source_line_no' => $lineNo],
            [
                'status' => 'confirmed',
                'movement_type' => 'opening_stock',
                'movement_date' => '2026-06-01',
                'stock_location_id' => $location->id,
                'unit_id' => $product->inventory_unit_id,
                'quantity' => $row['opening_quantity'],
                'source_shipment_header_id' => null,
                'source_shipment_line_id' => null,
                'related_stock_movement_id' => null,
                'production_lot_id' => $lot->id,
                'lot_code' => $lot->lot_code,
                'confirmed_at' => now(),
                'closed_at' => null,
                'cancelled_at' => null,
                'cancelled_reason' => null,
                'reason' => '架空デモ商品の初期在庫',
                'note' => '実在庫には使用しない。',
            ],
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function products(): array
    {
        return [
            $this->sake('001', '純米酒 朝凪 720ml', 'ジュンマイシュアサナギ', '純米', '60.00', '15.00', '720.0000', '1540.0000'),
            $this->sake('002', '純米酒 夕凪 1800ml', 'ジュンマイシュユウナギ', '純米', '60.00', '15.00', '1800.0000', '3080.0000'),
            $this->sake('003', '吟醸酒 青葉 720ml', 'ギンジョウシュアオバ', '吟醸', '55.00', '16.00', '720.0000', '1980.0000'),
            $this->sake('004', '大吟醸 白峰 720ml', 'ダイギンジョウシラミネ', '大吟醸', '45.00', '16.00', '720.0000', '3300.0000'),
            $this->sake('005', '本醸造 川音 1800ml', 'ホンジョウゾウカワオト', '本醸造', '65.00', '15.00', '1800.0000', '2420.0000'),
            $this->sake('006', '特別純米 星見 720ml', 'トクベツジュンマイホシミ', '特別純米', '58.00', '15.00', '720.0000', '1760.0000'),
            $this->sake('007', '生もと純米 山路 720ml', 'キモトジュンマイヤマジ', '生もと純米', '65.00', '16.00', '720.0000', '1870.0000'),
            $this->sake('008', 'にごり酒 雪あかり 720ml', 'ニゴリシュユキアカリ', 'にごり酒', '70.00', '14.00', '720.0000', '1430.0000'),
            $this->sake('009', '発泡清酒 花しずく 300ml', 'ハッポウセイシュハナシズク', '発泡清酒', '60.00', '12.00', '300.0000', '770.0000'),
            $this->sake('010', '原酒 森の雫 720ml', 'ゲンシュモリノシズク', '原酒', '60.00', '18.00', '720.0000', '2090.0000'),
            $this->sake('011', '熟成酒 琥珀 500ml', 'ジュクセイシュコハク', '熟成酒', '65.00', '17.00', '500.0000', '2640.0000'),
            $this->sake('012', '季節酒 春霞 720ml', 'キセツシュハルガスミ', '季節酒', '60.00', '14.00', '720.0000', '1650.0000'),
            $this->sake('013', '季節酒 夏風 720ml', 'キセツシュナツカゼ', '季節酒', '60.00', '14.00', '720.0000', '1650.0000'),
            $this->sake('014', '季節酒 秋灯 720ml', 'キセツシュアキアカリ', '季節酒', '60.00', '15.00', '720.0000', '1650.0000'),
            $this->sake('015', '季節酒 冬月 720ml', 'キセツシュフユヅキ', '季節酒', '60.00', '16.00', '720.0000', '1760.0000'),
            $this->sake('016', '生酒 若葉 720ml', 'ナマザケワカバ', '生酒', '60.00', '15.00', '720.0000', '1870.0000', '生', true, '冷蔵'),
            $this->sake('017', '山廃純米 夜長 1800ml', 'ヤマハイジュンマイヨナガ', '山廃純米', '65.00', '16.00', '1800.0000', '3520.0000'),
            $this->sake('018', '純米吟醸 水鏡 720ml', 'ジュンマイギンジョウミズカガミ', '純米吟醸', '50.00', '16.00', '720.0000', '2200.0000'),
            $this->food('019', '米こうじ甘酒 500ml', 'コメコウジアマザケ', '甘酒', '500.0000', '660.0000', '常温', '米こうじ甘酒'),
            $this->food('020', '黒米甘酒 500ml', 'クロマイアマザケ', '甘酒', '500.0000', '715.0000', '常温', '黒米甘酒'),
            $this->food('021', '酒粕あまざけ 300ml', 'サケカスアマザケ', '甘酒', '300.0000', '605.0000', '冷蔵', '酒粕甘酒'),
            $this->food('022', '麹調味料 200ml', 'コウジチョウミリョウ', '調味料', '200.0000', '550.0000', '常温', '発酵調味料'),
            $this->food('023', '酒粕クラッカー', 'サケカスクラッカー', '菓子', '120.0000', '440.0000', '常温', '菓子'),
            $this->kasu('024', '板粕 500g', 'イタカス', '500.0000', '550.0000', '板粕'),
            $this->kasu('025', '練り粕 1kg', 'ネリカス', '1000.0000', '880.0000', '練り粕'),
            $this->kasu('026', '吟醸酒粕 300g', 'ギンジョウサケカス', '300.0000', '660.0000', '吟醸酒粕'),
            $this->goods('027', 'お猪口 青磁', 'オチョコセイジ', '酒器', '陶器', '一合未満', '990.0000'),
            $this->goods('028', '帆布前掛け 紺', 'ハンプマエカケコン', '販促品', '帆布', 'フリーサイズ', '2530.0000'),
            $this->goods('029', '手ぬぐい 蔵模様', 'テヌグイクラモヨウ', '販促品', '綿', '約35cm x 90cm', '770.0000'),
        ];
    }

    /** @return array<string, string> */
    private function sake(string $number, string $name, string $kana, string $liquorType, string $polishingRatio, string $alcohol, string $capacity, string $price, string $styleName = '火入', bool $unpasteurized = false, string $storage = '常温'): array
    {
        return [
            'product_code' => "DEMO-PROD-{$number}", 'product_type' => 'sake', 'name' => $name, 'name_kana' => $kana,
            'series_name' => '清酒デモ', 'style_name' => $styleName, 'category_name' => '清酒', 'unit' => 'bottle', 'capacity_value' => $capacity, 'capacity_unit' => 'milliliter',
            'alcohol_percentage' => $alcohol, 'tax_category' => 'taxable_standard', 'unit_price' => $price, 'opening_quantity' => '60.0000',
            'liquor_type_name' => $liquorType, 'ingredients' => '米、米こうじ', 'rice_polishing_ratio' => $polishingRatio, 'production_method' => $liquorType,
            'is_unpasteurized' => $unpasteurized ? 'true' : 'false', 'detail_category' => '', 'storage_method' => $storage, 'shelf_life_days' => '0', 'material' => '', 'size_description' => '',
        ];
    }

    /** @return array<string, string> */
    private function food(string $number, string $name, string $kana, string $category, string $capacity, string $price, string $storage, string $detailCategory): array
    {
        return $this->nonLiquor($number, 'food', $name, $kana, $category, 'bottle', $capacity, 'milliliter', $price, $storage, $detailCategory);
    }

    /** @return array<string, string> */
    private function kasu(string $number, string $name, string $kana, string $capacity, string $price, string $detailCategory): array
    {
        return $this->nonLiquor($number, 'kasu', $name, $kana, '酒粕', 'bag', $capacity, 'gram', $price, '冷蔵', $detailCategory);
    }

    /** @return array<string, string> */
    private function goods(string $number, string $name, string $kana, string $detailCategory, string $material, string $size, string $price): array
    {
        $row = $this->nonLiquor($number, 'goods', $name, $kana, '物販', 'piece', '1.0000', 'gram', $price, '常温', $detailCategory);
        $row['capacity_value'] = '0.0000';
        $row['capacity_unit'] = 'gram';
        $row['tax_category'] = 'taxable_standard';
        $row['material'] = $material;
        $row['size_description'] = $size;

        return $row;
    }

    /** @return array<string, string> */
    private function nonLiquor(string $number, string $type, string $name, string $kana, string $category, string $unit, string $capacity, string $capacityUnit, string $price, string $storage, string $detailCategory): array
    {
        return [
            'product_code' => "DEMO-PROD-{$number}", 'product_type' => $type, 'name' => $name, 'name_kana' => $kana,
            'series_name' => '蔵元デモ', 'style_name' => $detailCategory, 'category_name' => $category, 'unit' => $unit, 'capacity_value' => $capacity, 'capacity_unit' => $capacityUnit,
            'alcohol_percentage' => '', 'tax_category' => 'taxable_reduced', 'unit_price' => $price, 'opening_quantity' => '40.0000',
            'liquor_type_name' => '', 'ingredients' => '', 'rice_polishing_ratio' => '', 'production_method' => '', 'is_unpasteurized' => 'false',
            'detail_category' => $detailCategory, 'storage_method' => $storage, 'shelf_life_days' => '90', 'material' => '', 'size_description' => '',
        ];
    }
}
