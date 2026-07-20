<?php

namespace Database\Seeders;

use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\SettlementReceivableCategory;
use App\Models\TransactionCategory;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoCustomerSeeder extends Seeder
{
    public function run(): void
    {
        $categories = TransactionCategory::query()
            ->whereIn('code', ['wholesale', 'retail'])
            ->get()
            ->keyBy('code');
        $settlements = SettlementReceivableCategory::query()
            ->whereIn('code', ['accounts_receivable_1', 'export'])
            ->get()
            ->keyBy('code');
        $billingCycles = BillingCycle::query()
            ->whereIn('code', ['monthly_end_next_month_end', 'cash_immediate'])
            ->get()
            ->keyBy('code');

        foreach ([
            '取引区分' => [$categories, ['wholesale', 'retail']],
            '決算売掛区分' => [$settlements, ['accounts_receivable_1', 'export']],
            '請求サイクル' => [$billingCycles, ['monthly_end_next_month_end', 'cash_immediate']],
        ] as $label => [$records, $codes]) {
            foreach ($codes as $code) {
                if (! $records->has($code)) {
                    throw new RuntimeException("デモ取引先の{$label} [{$code}] が見つかりません。");
                }
            }
        }

        foreach ($this->customers() as $customer) {
            $transactionCategory = $categories->get($customer['transaction_category']);
            $settlementCategory = $settlements->get($customer['settlement_category']);
            $billingCycle = $billingCycles->get($customer['billing_cycle']);

            if ($transactionCategory === null || $settlementCategory === null || $billingCycle === null) {
                throw new RuntimeException(
                    "デモ取引先 [{$customer['customer_code']}] の区分設定が不正です。".
                    " transaction={$customer['transaction_category']},".
                    " settlement={$customer['settlement_category']},".
                    " billing_cycle={$customer['billing_cycle']}",
                );
            }

            Customer::updateOrCreate(
                ['customer_code' => $customer['customer_code']],
                [
                    'name' => $customer['name'],
                    'name_kana' => $customer['name_kana'],
                    'short_name' => $customer['short_name'],
                    'billing_name' => $customer['name'],
                    'postal_code' => $customer['postal_code'],
                    'address1' => $customer['address1'],
                    'address2' => null,
                    'phone' => $customer['phone'],
                    'fax' => null,
                    'email' => $customer['email'],
                    'contact_name' => 'デモ担当',
                    'transaction_category_id' => $transactionCategory->id,
                    'settlement_receivable_category_id' => $settlementCategory->id,
                    'billing_cycle_id' => $billingCycle->id,
                    'tax_rounding_method' => 'round',
                    'tax_calculation_unit' => $customer['tax_calculation_unit'],
                    'amount_rounding_method' => 'round',
                    'invoice_required' => $customer['settlement_category'] !== 'export',
                    'search_key' => strtolower($customer['customer_code']).' '.$customer['name_kana'].' '.$customer['business_type'],
                    'legacy_code' => null,
                    'legacy_name' => null,
                    'note' => "架空のデモ取引先（{$customer['business_type']}）。実在の法人、住所、連絡先、取引関係とは無関係。",
                    'is_active' => true,
                    'disabled_at' => null,
                ],
            );
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function customers(): array
    {
        return [
            $this->customer('001', '北辰酒販株式会社', 'ホクセイシュハン', '北辰酒販', '架空県青葉市中央1-2-3', '卸売', 'wholesale', 'accounts_receivable_1', 'monthly_end_next_month_end', 'invoice'),
            $this->customer('002', '山桜酒類流通株式会社', 'ヤマザクラシュルイルウツウ', '山桜流通', '架空県桜川市本町2-4-6', '卸売', 'wholesale', 'accounts_receivable_1', 'monthly_end_next_month_end', 'invoice'),
            $this->customer('003', '東雲食品卸株式会社', 'シノノメショクヒンオロシ', '東雲食品卸', '架空県東雲市港3-5-2', '卸売', 'wholesale', 'accounts_receivable_1', 'monthly_end_next_month_end', 'invoice'),
            $this->customer('004', '西岳酒類株式会社', 'ニシタケシュルイ', '西岳酒類', '架空県西岳市駅前4-1-8', '卸売', 'wholesale', 'accounts_receivable_1', 'monthly_end_next_month_end', 'invoice'),
            $this->customer('005', '潮見商事株式会社', 'シオミショウジ', '潮見商事', '架空県潮見市新港5-3-1', '卸売', 'wholesale', 'accounts_receivable_1', 'monthly_end_next_month_end', 'invoice'),
            $this->customer('006', '緑野酒販有限会社', 'ミドリノシュハン', '緑野酒販', '架空県緑野市市場6-2-4', '卸売', 'wholesale', 'accounts_receivable_1', 'monthly_end_next_month_end', 'line'),
            $this->customer('007', '白峰流通株式会社', 'シラミネリュウツウ', '白峰流通', '架空県白峰市中通7-6-3', '卸売', 'wholesale', 'accounts_receivable_1', 'monthly_end_next_month_end', 'invoice'),
            $this->customer('008', '大川食品販売株式会社', 'オオカワショクヒンハンバイ', '大川食品販売', '架空県大川市南町8-2-9', '卸売', 'wholesale', 'accounts_receivable_1', 'monthly_end_next_month_end', 'invoice'),
            $this->customer('009', '酒舗あかね屋', 'シュホアカネヤ', 'あかね屋', '架空県青葉市若葉9-1-5', '小売', 'retail', 'accounts_receivable_1', 'monthly_end_next_month_end', 'line'),
            $this->customer('010', '酒の月見堂', 'サケノツキミドウ', '月見堂', '架空県桜川市月見10-3-7', '小売', 'retail', 'accounts_receivable_1', 'monthly_end_next_month_end', 'invoice'),
            $this->customer('011', '地酒処みなと屋', 'ジザケドコロミナトヤ', 'みなと屋', '架空県東雲市海岸11-4-2', '小売', 'retail', 'accounts_receivable_1', 'monthly_end_next_month_end', 'line'),
            $this->customer('012', '酒蔵前商店', 'サカグラマエショウテン', '酒蔵前商店', '架空県西岳市蔵前12-2-6', '小売', 'retail', 'accounts_receivable_1', 'cash_immediate', 'line'),
            $this->customer('013', '食卓市場こもれび', 'ショクタクイチバコモレビ', 'こもれび', '架空県潮見市日向13-7-4', '小売', 'retail', 'accounts_receivable_1', 'monthly_end_next_month_end', 'invoice'),
            $this->customer('014', '酒と食の小径', 'サケトショクノコミチ', '小径', '架空県緑野市小径14-2-1', '小売', 'retail', 'accounts_receivable_1', 'cash_immediate', 'line'),
            $this->customer('015', '山里百貨店食品部', 'ヤマザトヒャッカテンショクヒンブ', '山里百貨店', '架空県白峰市山里15-5-8', '小売', 'retail', 'accounts_receivable_1', 'monthly_end_next_month_end', 'invoice'),
            $this->customer('016', '大川駅前酒店', 'オオカワエキマエサケテン', '駅前酒店', '架空県大川市駅前16-1-3', '小売', 'retail', 'accounts_receivable_1', 'cash_immediate', 'line'),
            $this->customer('017', '旬菜割烹あさひ', 'シュンサイカッポウアサヒ', '割烹あさひ', '架空県青葉市朝日17-3-6', '飲食', 'retail', 'accounts_receivable_1', 'monthly_end_next_month_end', 'invoice'),
            $this->customer('018', '炭火料理さざなみ', 'スミビリョウリサザナミ', 'さざなみ', '架空県桜川市川端18-4-5', '飲食', 'retail', 'accounts_receivable_1', 'monthly_end_next_month_end', 'invoice'),
            $this->customer('019', '和食処いろは', 'ワショクドコロイロハ', '和食いろは', '架空県東雲市花町19-2-7', '飲食', 'retail', 'accounts_receivable_1', 'monthly_end_next_month_end', 'line'),
            $this->customer('020', '旅館みどり亭', 'リョカンミドリテイ', 'みどり亭', '架空県西岳市温泉20-6-2', '飲食', 'retail', 'accounts_receivable_1', 'monthly_end_next_month_end', 'invoice'),
            $this->customer('021', '食事処うみの灯', 'ショクジドコロウミノアカリ', 'うみの灯', '架空県潮見市浜通21-5-9', '飲食', 'retail', 'accounts_receivable_1', 'monthly_end_next_month_end', 'line'),
            $this->customer('022', '宿場食堂まつかぜ', 'シュクバショクドウマツカゼ', 'まつかぜ', '架空県緑野市宿場22-1-4', '飲食', 'retail', 'accounts_receivable_1', 'cash_immediate', 'line'),
            $this->customer('023', '白峰温泉ホテル', 'シラミネオンセンホテル', '白峰ホテル', '架空県白峰市温泉郷23-8-1', '飲食', 'retail', 'accounts_receivable_1', 'monthly_end_next_month_end', 'invoice'),
            $this->customer('024', '川辺レストラン結', 'カワベレストランユイ', 'レストラン結', '架空県大川市川辺24-3-2', '飲食', 'retail', 'accounts_receivable_1', 'monthly_end_next_month_end', 'invoice'),
            $this->customer('025', 'North Star Sake Trading', 'ノーススターサケトレーディング', 'North Star', 'Fictional City, Overseas District 25', '輸出', 'wholesale', 'export', 'monthly_end_next_month_end', 'invoice'),
            $this->customer('026', 'Harbor Gate Imports', 'ハーバーゲートインポーツ', 'Harbor Gate', 'Fictional Port, Overseas District 26', '輸出', 'wholesale', 'export', 'monthly_end_next_month_end', 'invoice'),
            $this->customer('027', 'Cedar Bridge Beverage', 'シダーブリッジビバレッジ', 'Cedar Bridge', 'Fictional Town, Overseas District 27', '輸出', 'wholesale', 'export', 'monthly_end_next_month_end', 'invoice'),
            $this->customer('028', '明日香ギフト株式会社', 'アスカギフト', '明日香ギフト', '架空県青葉市贈答28-2-3', '法人小売', 'retail', 'accounts_receivable_1', 'monthly_end_next_month_end', 'invoice'),
            $this->customer('029', '四季彩堂オンライン店', 'シキサイドウオンラインテン', '四季彩堂', '架空県桜川市四季29-4-1', '通販', 'retail', 'accounts_receivable_1', 'monthly_end_next_month_end', 'invoice'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function customer(
        string $number,
        string $name,
        string $nameKana,
        string $shortName,
        string $address1,
        string $businessType,
        string $transactionCategory,
        string $settlementCategory,
        string $billingCycle,
        string $taxCalculationUnit,
    ): array {
        return [
            'customer_code' => "DEMO-CUST-{$number}",
            'name' => $name,
            'name_kana' => $nameKana,
            'short_name' => $shortName,
            'postal_code' => "000-{$number}",
            'address1' => $address1,
            'business_type' => $businessType,
            'phone' => "000-0000-{$number}",
            'email' => "customer{$number}@example.test",
            'transaction_category' => $transactionCategory,
            'settlement_category' => $settlementCategory,
            'billing_cycle' => $billingCycle,
            'tax_calculation_unit' => $taxCalculationUnit,
        ];
    }
}
