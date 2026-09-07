<?php

namespace Database\Seeders;

use App\Models\BillingCycle;
use App\Models\ConsumptionTaxCategory;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\SakeProductDetail;
use App\Models\SalesOrder;
use App\Models\SettlementReceivableCategory;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Models\UnitConversion;
use App\Services\SalesOrder\CreateSalesOrderData;
use App\Services\SalesOrder\CreateSalesOrderLineData;
use App\Services\SalesOrder\CreateSalesOrderService;
use App\Services\Shipment\ApplyDraftShipmentPricingService;
use App\Services\Shipment\ConfirmShipmentService;
use App\Services\Shipment\CreateDraftShipmentFromInstructionService;
use App\Services\ShipmentInstruction\CreateShipmentInstructionData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionLineData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionService;
use App\Services\ShipmentPicking\PickShipmentInstructionData;
use App\Services\ShipmentPicking\PickShipmentInstructionLineData;
use App\Services\ShipmentPicking\PickShipmentInstructionService;
use Illuminate\Database\Seeder;

class ArimitsuDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CustomerMasterSeeder::class,
            ProductUnitMasterSeeder::class,
            PriceMasterSeeder::class,
            TaxMasterSeeder::class,
            LiquorTaxMasterSeeder::class,
            StockLocationSeeder::class,
            ShipmentMasterSeeder::class,
        ]);

        $this->createProducts();
    }

    private function createCustomers(): void
    {
        $categories = TransactionCategory::query()->whereIn('code', ['producer', 'wholesale', 'retail'])->get()->keyBy('code');
        $settlement = SettlementReceivableCategory::query()->where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::query()->where('code', 'monthly_end_next_month_end')->firstOrFail();

        foreach ([
            ['code' => 'ARI-CUST-001', 'name' => '土佐中央酒販株式会社', 'kana' => 'トサチュウオウシュハン', 'category' => 'wholesale', 'address' => '高知県高知市桟橋通1-1-1'],
            ['code' => 'ARI-CUST-002', 'name' => '安芸東部酒店', 'kana' => 'アキトウブサケテン', 'category' => 'retail', 'address' => '高知県安芸市本町2-2-2'],
            ['code' => 'ARI-CUST-003', 'name' => '四国地酒流通株式会社', 'kana' => 'シコクジザケリュウツウ', 'category' => 'wholesale', 'address' => '香川県高松市番町3-3-3'],
            ['code' => 'ARI-CUST-004', 'name' => '東京銘酒小売店', 'kana' => 'トウキョウメイシュコウリテン', 'category' => 'retail', 'address' => '東京都中央区日本橋4-4-4'],
            ['code' => 'ARI-CUST-005', 'name' => '高知県産品協同組合', 'kana' => 'コウチケンサンピンキョウドウクミアイ', 'category' => 'producer', 'address' => '高知県南国市大そね5-5-5'],
        ] as $row) {
            Customer::updateOrCreate(
                ['customer_code' => $row['code']],
                [
                    'name' => $row['name'],
                    'name_kana' => $row['kana'],
                    'short_name' => $row['name'],
                    'billing_name' => $row['name'],
                    'postal_code' => '780-0000',
                    'address1' => $row['address'],
                    'phone' => '088-000-0000',
                    'fax' => '088-000-0001',
                    'email' => strtolower($row['code']).'@example.test',
                    'contact_name' => 'デモ担当',
                    'transaction_category_id' => $categories->get($row['category'])->id,
                    'settlement_receivable_category_id' => $settlement->id,
                    'billing_cycle_id' => $billingCycle->id,
                    'tax_rounding_method' => 'round',
                    'tax_calculation_unit' => 'line',
                    'amount_rounding_method' => 'round',
                    'invoice_required' => true,
                    'search_key' => strtolower($row['code']).' '.$row['kana'].' '.$row['name'],
                    'note' => '有光酒造場デモ用の架空取引先です。実在の取引関係ではありません。',
                    'is_active' => true,
                    'disabled_at' => null,
                ],
            );
        }
    }

    private function createProducts(): void
    {
        $bottle = Unit::query()->where('code', 'bottle')->firstOrFail();
        $liter = Unit::query()->where('code', 'liter')->firstOrFail();
        $milliliter = Unit::query()->where('code', 'milliliter')->firstOrFail();
        $tax = ConsumptionTaxCategory::query()->where('code', 'taxable_standard')->firstOrFail();
        $location = StockLocation::query()->where('code', 'main_brewery')->firstOrFail();
        $priceLists = PriceList::query()->whereIn('code', ['common', 'producer_price', 'wholesale_price', 'retail_price', 'customer_price'])->get()->keyBy('code');
        $categories = TransactionCategory::query()->whereIn('code', ['producer', 'wholesale', 'retail'])->get()->keyBy('code');
        $specialCustomer = Customer::query()->where('customer_code', 'ARI-CUST-001')->first();

        $lineNo = 1;
        foreach ($this->products() as $row) {
            foreach ($row['volumes'] as $volume) {
                $code = sprintf('AKITORA-%03d-%s', $lineNo, $volume);
                $retailPrice = $this->retailPrice($row['class'], (int) $volume);
                $product = Product::updateOrCreate(
                    ['product_code' => $code],
                    [
                        'product_type' => 'sake',
                        'name' => "{$row['name']} {$volume}ml",
                        'name_kana' => $row['kana'],
                        'display_name' => "{$row['name']} {$volume}ml",
                        'brand_name' => '安芸虎',
                        'series_name' => '安芸虎',
                        'style_name' => $row['style'],
                        'category_name' => $row['class'],
                        'consumption_tax_category_id' => $tax->id,
                        'base_unit_id' => $bottle->id,
                        'sales_unit_id' => $bottle->id,
                        'inventory_unit_id' => $bottle->id,
                        'capacity_value' => number_format((float) $volume, 4, '.', ''),
                        'capacity_unit_id' => $milliliter->id,
                        'alcohol_percentage' => $row['alcohol'],
                        'is_alcohol' => true,
                        'is_sales_available' => true,
                        'is_inventory_managed' => true,
                        'search_key' => strtolower($code).' '.$row['kana'].' '.$row['name'].' 安芸虎 有光酒造場 '.$row['class'].' '.$volume.'ml',
                        'legacy_code' => null,
                        'legacy_name' => null,
                        'note' => '有光酒造場公式の商品一覧をもとにしたデモ商品です。価格・在庫・ロットは動作確認用の架空データです。',
                        'is_active' => true,
                        'disabled_at' => null,
                    ],
                );

                SakeProductDetail::updateOrCreate(
                    ['product_id' => $product->id],
                    [
                        'liquor_tax_category_code' => 'seishu',
                        'liquor_type_name' => $row['class'],
                        'ingredients' => '米、米こうじ',
                        'rice_polishing_ratio' => $row['polishing'],
                        'production_method' => $row['style'],
                        'is_unpasteurized' => $row['raw'],
                    ],
                );

                UnitConversion::updateOrCreate(
                    ['product_id' => $product->id, 'from_unit_id' => $bottle->id, 'to_unit_id' => $liter->id],
                    ['factor' => bcdiv((string) $volume, '1000', 6), 'rounding_method' => 'none', 'is_active' => true],
                );

                $this->createPrices($product, $priceLists, $categories, $specialCustomer, $retailPrice);
                $this->createLotAndStock($product, $location, $lineNo);
                $lineNo++;
            }
        }
    }

    /**
     * @param  array<string, PriceList>  $priceLists
     * @param  array<string, TransactionCategory>  $categories
     */
    private function createPrices(Product $product, $priceLists, $categories, ?Customer $specialCustomer, int $retailPrice): void
    {
        $prices = [
            ['list' => 'producer_price', 'category' => 'producer', 'price' => (int) round($retailPrice * 0.55), 'priority' => 220],
            ['list' => 'wholesale_price', 'category' => 'wholesale', 'price' => (int) round($retailPrice * 0.72), 'priority' => 210],
            ['list' => 'retail_price', 'category' => 'retail', 'price' => $retailPrice, 'priority' => 200],
            ['list' => 'common', 'category' => null, 'price' => $retailPrice, 'priority' => 500],
        ];

        foreach ($prices as $row) {
            PriceRule::updateOrCreate(
                [
                    'price_list_id' => $priceLists->get($row['list'])->id,
                    'product_id' => $product->id,
                    'customer_id' => null,
                    'transaction_category_id' => $row['category'] ? $categories->get($row['category'])->id : null,
                    'unit_id' => $product->sales_unit_id,
                    'effective_from' => '2026-06-01',
                ],
                [
                    'unit_price' => number_format($row['price'], 4, '.', ''),
                    'currency' => 'JPY',
                    'priority' => $row['priority'],
                    'effective_to' => null,
                    'rounding_method' => 'round',
                    'reason' => '有光酒造場デモ価格。価格は架空。',
                    'is_active' => true,
                ],
            );
        }

        if ($specialCustomer && (str_ends_with($product->product_code, '720') || str_ends_with($product->product_code, '1800'))) {
            PriceRule::updateOrCreate(
                [
                    'price_list_id' => $priceLists->get('customer_price')->id,
                    'product_id' => $product->id,
                    'customer_id' => $specialCustomer->id,
                    'transaction_category_id' => null,
                    'unit_id' => $product->sales_unit_id,
                    'effective_from' => '2026-06-01',
                ],
                [
                    'unit_price' => number_format((int) round($retailPrice * 0.68), 4, '.', ''),
                    'currency' => 'JPY',
                    'priority' => 100,
                    'effective_to' => null,
                    'rounding_method' => 'round',
                    'reason' => '有光酒造場デモの取引先個別価格。価格は架空。',
                    'is_active' => true,
                ],
            );
        }
    }

    private function createLotAndStock(Product $product, StockLocation $location, int $lineNo): void
    {
        $lot = ProductionLot::updateOrCreate(
            ['lot_code' => sprintf('AKITORA-LOT-%03d-202606', $lineNo)],
            [
                'display_name' => "{$product->display_name} 2026年6月デモロット",
                'status' => 'active',
                'stock_location_id' => $location->id,
                'unit_id' => $product->inventory_unit_id,
                'capacity_value' => $product->capacity_value,
                'capacity_unit_id' => $product->capacity_unit_id,
                'alcohol_percentage' => $product->alcohol_percentage,
                'analysis_status' => $product->is_alcohol ? 'confirmed' : null,
                'production_date' => '2026-05-20',
                'bottling_date' => '2026-06-01',
                'tank_code' => 'AKITORA-DEMO-TANK',
                'rice_variety' => 'デモ酒米',
                'rice_polishing_ratio' => $product->sakeDetail?->rice_polishing_ratio,
                'production_method' => $product->style_name,
                'storage_condition' => str_contains($product->name, '生') ? '冷蔵' : '常温',
                'search_key' => strtolower($product->product_code).' '.$product->display_name.' 有光酒造場 安芸虎',
                'note' => '有光酒造場デモ用の架空製造ロットです。',
                'is_active' => true,
                'disabled_at' => null,
            ],
        );

        StockMovement::updateOrCreate(
            ['source_type' => 'arimitsu_demo_seeder', 'source_document_number' => 'AKITORA-DEMO-OPENING-202606', 'source_line_no' => $lineNo],
            [
                'status' => 'confirmed',
                'movement_type' => 'opening_stock',
                'movement_date' => '2026-06-01',
                'stock_location_id' => $location->id,
                'unit_id' => $product->inventory_unit_id,
                'quantity' => '48.0000',
                'production_lot_id' => $lot->id,
                'lot_code' => $lot->lot_code,
                'confirmed_at' => now(),
                'reason' => '有光酒造場デモ商品の初期在庫',
                'note' => '動作確認用の架空在庫です。',
            ],
        );
    }

    private function createTransactions(): void
    {
        if (SalesOrder::query()->where('source_type', 'arimitsu_demo')->exists()) {
            return;
        }

        $customers = Customer::query()
            ->where('customer_code', 'like', 'ARI-CUST-%')
            ->orderBy('customer_code')
            ->get()
            ->values();
        $products = Product::query()
            ->where('product_code', 'like', 'AKITORA-%')
            ->orderBy('product_code')
            ->limit(18)
            ->get()
            ->values();
        $location = StockLocation::query()
            ->where('is_default_shipping_location', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->firstOrFail();

        $createOrder = app(CreateSalesOrderService::class);
        $createInstruction = app(CreateShipmentInstructionService::class);
        $pickService = app(PickShipmentInstructionService::class);
        $draftShipment = app(CreateDraftShipmentFromInstructionService::class);
        $priceShipment = app(ApplyDraftShipmentPricingService::class);
        $confirmShipment = app(ConfirmShipmentService::class);

        foreach (range(1, 15) as $number) {
            $customer = $customers[($number - 1) % $customers->count()];
            $first = $products[(($number - 1) * 2) % $products->count()];
            $second = $products[((($number - 1) * 2) + 1) % $products->count()];
            $orderDate = now()->subDays(16 - $number)->toDateString();
            $shipmentDate = now()->addDays(($number % 5) + 1)->toDateString();
            $reference = sprintf('arimitsu-demo-flow-%02d', $number);

            $order = $createOrder->create(new CreateSalesOrderData(
                customerId: $customer->id,
                orderDate: $orderDate,
                requestedShipmentDate: $shipmentDate,
                requestedDeliveryDate: now()->addDays(($number % 5) + 2)->toDateString(),
                customerOrderNumber: sprintf('AKITORA-DEMO-%04d', $number),
                sourceType: 'arimitsu_demo',
                sourceReference: $reference,
                note: '有光酒造場デモ取引用の受注です。',
                reason: '有光酒造場デモデータ作成',
                applyPricing: true,
                awaitingShipmentInstruction: $number <= 4,
                lines: [
                    new CreateSalesOrderLineData($first->id, number_format(3 + ($number % 4), 4, '.', ''), $first->sales_unit_id),
                    new CreateSalesOrderLineData($second->id, number_format(2 + ($number % 3), 4, '.', ''), $second->sales_unit_id),
                ],
            ));

            if ($number <= 4) {
                continue;
            }

            $instruction = $createInstruction->create(new CreateShipmentInstructionData(
                instructionDate: $orderDate,
                scheduledShipmentDate: $shipmentDate,
                stockLocationId: $location->id,
                note: '有光酒造場デモ取引用の出荷指示です。',
                reason: '有光酒造場デモデータ作成',
                lines: $order->lines
                    ->map(fn ($line) => new CreateShipmentInstructionLineData($line->id, (string) $line->quantity))
                    ->all(),
            ));

            if ($number <= 8) {
                continue;
            }

            $pick = $pickService->pick(new PickShipmentInstructionData(
                shipmentInstructionId: $instruction->id,
                pickDate: $shipmentDate,
                stockLocationId: $location->id,
                note: '有光酒造場デモ取引用のピッキングです。',
                reason: '有光酒造場デモデータ作成',
                lines: $instruction->lines
                    ->map(fn ($line) => new PickShipmentInstructionLineData($line->id, (string) $line->quantity))
                    ->all(),
            ));

            if ($number <= 11) {
                continue;
            }

            $shipment = $draftShipment->create($instruction);

            if ($number <= 13) {
                continue;
            }

            $shipment = $priceShipment->apply($shipment, '有光酒造場デモデータ作成');
            $confirmShipment->confirm($shipment, '有光酒造場デモデータ作成');
        }
    }

    private function retailPrice(string $class, int $volume): int
    {
        $base = match ($class) {
            '純米大吟醸' => 3200,
            '純米吟醸' => 1900,
            '純米' => 1450,
            '吟醸' => 1300,
            default => 1800,
        };

        return match ($volume) {
            300, 330 => (int) round($base * 0.46),
            720 => $base,
            1800 => (int) round($base * 2.35),
            default => $base,
        };
    }

    /**
     * @return array<int, array{name: string, kana: string, class: string, style: string, polishing: string, alcohol: string, raw: bool, volumes: array<int, int>}>
     */
    private function products(): array
    {
        return [
            ['name' => '安芸虎 山田錦40％精米 純米大吟醸', 'kana' => 'アキトラヤマダニシキジュンマイダイギンジョウ', 'class' => '純米大吟醸', 'style' => '山田錦40％精米', 'polishing' => '40.00', 'alcohol' => '16.00', 'raw' => false, 'volumes' => [720, 1800]],
            ['name' => '安芸虎 純米大吟醸 赤ラベル', 'kana' => 'アキトラジュンマイダイギンジョウアカラベル', 'class' => '純米大吟醸', 'style' => '赤ラベル', 'polishing' => '50.00', 'alcohol' => '16.00', 'raw' => false, 'volumes' => [720, 1800]],
            ['name' => '安芸虎 深海の宇宙 純米大吟醸', 'kana' => 'アキトラシンカイノソラジュンマイダイギンジョウ', 'class' => '純米大吟醸', 'style' => '宇宙深海酵母', 'polishing' => '50.00', 'alcohol' => '16.00', 'raw' => false, 'volumes' => [720]],
            ['name' => '安芸虎 CEL-24 純米大吟醸', 'kana' => 'アキトラセルニジュウヨンジュンマイダイギンジョウ', 'class' => '純米大吟醸', 'style' => 'CEL-24', 'polishing' => '50.00', 'alcohol' => '15.00', 'raw' => false, 'volumes' => [720]],
            ['name' => '安芸虎 雄町 純米大吟醸', 'kana' => 'アキトラオマチジュンマイダイギンジョウ', 'class' => '純米大吟醸', 'style' => '雄町', 'polishing' => '50.00', 'alcohol' => '16.00', 'raw' => false, 'volumes' => [720, 1800]],
            ['name' => '安芸虎 純米吟醸', 'kana' => 'アキトラジュンマイギンジョウ', 'class' => '純米吟醸', 'style' => '定番', 'polishing' => '55.00', 'alcohol' => '15.00', 'raw' => false, 'volumes' => [300, 720, 1800]],
            ['name' => '安芸虎 純米吟醸 たれくち 生酒', 'kana' => 'アキトラジュンマイギンジョウタレクチナマザケ', 'class' => '純米吟醸', 'style' => 'たれくち 生酒', 'polishing' => '55.00', 'alcohol' => '15.00', 'raw' => true, 'volumes' => [300, 720, 1800]],
            ['name' => '安芸虎 入河内 純米吟醸', 'kana' => 'アキトラニュウガウチジュンマイギンジョウ', 'class' => '純米吟醸', 'style' => '入河内', 'polishing' => '55.00', 'alcohol' => '15.00', 'raw' => false, 'volumes' => [300, 720, 1800]],
            ['name' => '安芸虎 山田錦50％精米 純米吟醸', 'kana' => 'アキトラヤマダニシキジュンマイギンジョウ', 'class' => '純米吟醸', 'style' => '山田錦50％精米', 'polishing' => '50.00', 'alcohol' => '16.00', 'raw' => false, 'volumes' => [720, 1800]],
            ['name' => '安芸虎 ライト 純米吟醸原酒', 'kana' => 'アキトラライトジュンマイギンジョウゲンシュ', 'class' => '純米吟醸', 'style' => 'ライト 原酒', 'polishing' => '55.00', 'alcohol' => '13.00', 'raw' => false, 'volumes' => [720, 1800]],
            ['name' => '安芸虎 朝日 純米吟醸', 'kana' => 'アキトラアサヒジュンマイギンジョウ', 'class' => '純米吟醸', 'style' => '朝日', 'polishing' => '55.00', 'alcohol' => '15.00', 'raw' => false, 'volumes' => [720, 1800]],
            ['name' => '安芸虎 素 純米吟醸 発泡生酒', 'kana' => 'アキトラソジュンマイギンジョウハッポウナマザケ', 'class' => '純米吟醸', 'style' => '発泡生酒', 'polishing' => '55.00', 'alcohol' => '14.00', 'raw' => true, 'volumes' => [330, 720, 1800]],
            ['name' => '安芸虎 山田錦80％精米 純米', 'kana' => 'アキトラヤマダニシキジュンマイ', 'class' => '純米', 'style' => '山田錦80％精米', 'polishing' => '80.00', 'alcohol' => '15.00', 'raw' => false, 'volumes' => [720, 1800]],
            ['name' => '安芸虎 雄町82％精米 純米', 'kana' => 'アキトラオマチジュンマイ', 'class' => '純米', 'style' => '雄町82％精米', 'polishing' => '82.00', 'alcohol' => '15.00', 'raw' => false, 'volumes' => [720, 1800]],
            ['name' => '安芸虎 純米', 'kana' => 'アキトラジュンマイ', 'class' => '純米', 'style' => '定番', 'polishing' => '60.00', 'alcohol' => '15.00', 'raw' => false, 'volumes' => [300, 720, 1800]],
            ['name' => '安芸虎 山田錦60％精米 純米', 'kana' => 'アキトラヤマダニシキロクジュッパージュンマイ', 'class' => '純米', 'style' => '山田錦60％精米', 'polishing' => '60.00', 'alcohol' => '15.00', 'raw' => false, 'volumes' => [720, 1800]],
            ['name' => '安芸虎 超辛口吟醸', 'kana' => 'アキトラチョウカラクチギンジョウ', 'class' => '吟醸', 'style' => '超辛口', 'polishing' => '60.00', 'alcohol' => '15.00', 'raw' => false, 'volumes' => [300, 720, 1800]],
            ['name' => '安芸虎 土佐麗しぼりたて 純米吟醸 生', 'kana' => 'アキトラトサウララシボリタテジュンマイギンジョウナマ', 'class' => '季節限定', 'style' => '土佐麗しぼりたて', 'polishing' => '55.00', 'alcohol' => '16.00', 'raw' => true, 'volumes' => [720, 1800]],
            ['name' => '安芸虎 夏純吟', 'kana' => 'アキトラナツジュンギン', 'class' => '季節限定', 'style' => '夏純吟', 'polishing' => '55.00', 'alcohol' => '15.00', 'raw' => false, 'volumes' => [720, 1800]],
            ['name' => '安芸虎 雄町ひやおろし 純米吟醸', 'kana' => 'アキトラオマチヒヤオロシジュンマイギンジョウ', 'class' => '季節限定', 'style' => '雄町ひやおろし', 'polishing' => '55.00', 'alcohol' => '15.00', 'raw' => false, 'volumes' => [720, 1800]],
        ];
    }
}
