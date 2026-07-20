<?php

namespace Database\Seeders;

use App\Models\LiquorTaxAdjustmentSetting;
use App\Models\LiquorTaxCategory;
use App\Models\LiquorTaxReliefSetting;
use App\Models\LiquorTaxRule;
use Illuminate\Database\Seeder;

class LiquorTaxMasterSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->categories() as $category) {
            LiquorTaxCategory::updateOrCreate(
                ['code' => $category['code']],
                [
                    'name' => $category['name'],
                    'taxability' => $category['taxability'],
                    'aggregate0' => $category['aggregate0'],
                    'aggregate1' => $category['aggregate1'],
                    'aggregate2' => $category['aggregate2'],
                    'aggregate3' => $category['aggregate3'],
                    'print_order' => $category['print_order'],
                    'reduction_rate' => $category['reduction_rate'],
                    'is_active' => true,
                ],
            );
        }

        $seishu = LiquorTaxCategory::where('code', 'seishu')->firstOrFail();

        LiquorTaxRule::updateOrCreate(
            ['code' => 'seishu_fixed_per_kl_2023_10_01'],
            [
                'liquor_tax_category_id' => $seishu->id,
                'name' => 'Seishu fixed tax per kL from 2023-10-01',
                'calculation_method' => 'fixed_per_kl',
                'tax_per_kl' => '100000.0000',
                'alcohol_percentage_min' => null,
                'alcohol_percentage_max' => null,
                'base_alcohol_percentage' => null,
                'additional_tax_per_kl_per_percent' => null,
                'reduction_rate' => $seishu->reduction_rate,
                'special_provision_code' => null,
                'effective_from' => '2023-10-01',
                'effective_to' => '2026-09-30',
                'description' => 'Seeded as a current-period base rule; official rates must be reviewed when adding future periods.',
                'is_active' => true,
            ],
        );

        LiquorTaxRule::updateOrCreate(
            ['code' => 'seishu_fixed_per_kl_2026_10_01'],
            [
                'liquor_tax_category_id' => $seishu->id,
                'name' => '清酒 2026-10-01以後',
                'calculation_method' => 'fixed_per_kl',
                'tax_per_kl' => '100000.0000',
                'reduction_rate' => '0.0000',
                'effective_from' => '2026-10-01',
                'effective_to' => null,
                'description' => '令和8年10月1日以後の清酒基本税率。',
                'is_active' => true,
            ],
        );

        $liqueur = LiquorTaxCategory::where('code', 'liqueur')->firstOrFail();
        LiquorTaxRule::updateOrCreate(
            ['code' => 'liqueur_per_degree_2023_10_01'],
            [
                'liquor_tax_category_id' => $liqueur->id,
                'name' => 'リキュール 2023-10-01から2026-09-30',
                'calculation_method' => 'per_degree',
                'tax_per_kl' => '80000.0000',
                'base_alcohol_percentage' => '8.00',
                'additional_tax_per_kl_per_percent' => '10000.0000',
                'reduction_rate' => '0.0000',
                'effective_from' => '2023-10-01',
                'effective_to' => '2026-09-30',
                'description' => '9度未満80,000円、8度を超える1度ごとに10,000円加算。',
                'is_active' => true,
            ],
        );
        LiquorTaxRule::updateOrCreate(
            ['code' => 'liqueur_per_degree_2026_10_01'],
            [
                'liquor_tax_category_id' => $liqueur->id,
                'name' => 'リキュール 2026-10-01以後',
                'calculation_method' => 'per_degree',
                'tax_per_kl' => '100000.0000',
                'base_alcohol_percentage' => '10.00',
                'additional_tax_per_kl_per_percent' => '10000.0000',
                'reduction_rate' => '0.0000',
                'effective_from' => '2026-10-01',
                'effective_to' => null,
                'description' => '11度未満100,000円、10度を超える1度ごとに10,000円加算。',
                'is_active' => true,
            ],
        );

        LiquorTaxReliefSetting::updateOrCreate(
            ['manufacturing_site_code' => 'main', 'effective_from' => '2024-04-01'],
            [
                'scheme' => 'legacy_scheme',
                'effective_to' => null,
                'legacy_reduction_rate' => '0.2000',
                'legacy_annual_quantity_limit_kl' => '200.000000',
                'opening_eligible_quantity_kl' => '0.000000',
                'opening_gross_tax_amount' => '0.00',
                'calculation_rule_version' => 'legacy-2024-v1',
                'note' => '旧制度選択届出済みの蔵向け初期設定。届出情報は本番開始前に更新する。',
                'is_active' => true,
            ],
        );

        LiquorTaxAdjustmentSetting::updateOrCreate(
            ['manufacturing_site_code' => 'main'],
            ['approval_amount_threshold' => '0.00', 'approval_quantity_threshold_kl' => '0.000000', 'is_active' => true],
        );
    }

    /**
     * @return array<int, array<string, string|int>>
     */
    private function categories(): array
    {
        return [
            [
                'code' => 'seishu',
                'name' => 'Seishu',
                'taxability' => 'taxable',
                'aggregate0' => 'Alcohol',
                'aggregate1' => 'Brewed liquor',
                'aggregate2' => 'Seishu',
                'aggregate3' => null,
                'print_order' => 10,
                'reduction_rate' => '0.0000',
            ],
            [
                'code' => 'liqueur',
                'name' => 'Liqueur',
                'taxability' => 'taxable',
                'aggregate0' => 'Alcohol',
                'aggregate1' => 'Mixed liquor',
                'aggregate2' => 'Liqueur',
                'aggregate3' => null,
                'print_order' => 20,
                'reduction_rate' => '0.0000',
            ],
            [
                'code' => 'other_brewed_liquor',
                'name' => 'Other brewed liquor',
                'taxability' => 'taxable',
                'aggregate0' => 'Alcohol',
                'aggregate1' => 'Brewed liquor',
                'aggregate2' => 'Other brewed liquor',
                'aggregate3' => null,
                'print_order' => 30,
                'reduction_rate' => '0.0000',
            ],
            [
                'code' => 'spirits',
                'name' => 'Spirits',
                'taxability' => 'taxable',
                'aggregate0' => 'Alcohol',
                'aggregate1' => 'Distilled liquor',
                'aggregate2' => 'Spirits',
                'aggregate3' => null,
                'print_order' => 40,
                'reduction_rate' => '0.0000',
            ],
            [
                'code' => 'mirin',
                'name' => 'Mirin',
                'taxability' => 'taxable',
                'aggregate0' => 'Alcohol',
                'aggregate1' => 'Seasoning liquor',
                'aggregate2' => 'Mirin',
                'aggregate3' => null,
                'print_order' => 50,
                'reduction_rate' => '0.0000',
            ],
            [
                'code' => 'beer',
                'name' => 'Beer',
                'taxability' => 'taxable',
                'aggregate0' => 'Alcohol',
                'aggregate1' => 'Beer',
                'aggregate2' => 'Beer',
                'aggregate3' => null,
                'print_order' => 60,
                'reduction_rate' => '0.0000',
            ],
            [
                'code' => 'export_exempt_liquor',
                'name' => 'Export exempt liquor',
                'taxability' => 'exempt',
                'aggregate0' => 'Alcohol',
                'aggregate1' => 'Export',
                'aggregate2' => 'Export exempt',
                'aggregate3' => null,
                'print_order' => 900,
                'reduction_rate' => '0.0000',
            ],
            [
                'code' => 'untaxed_transfer_liquor',
                'name' => '未納税移出酒類',
                'taxability' => 'untaxed',
                'aggregate0' => 'Alcohol',
                'aggregate1' => 'Untaxed transfer',
                'aggregate2' => 'Untaxed transfer',
                'aggregate3' => null,
                'print_order' => 910,
                'reduction_rate' => '0.0000',
            ],
            [
                'code' => 'non_liquor',
                'name' => 'Non liquor',
                'taxability' => 'out_of_scope',
                'aggregate0' => 'Non liquor',
                'aggregate1' => null,
                'aggregate2' => null,
                'aggregate3' => null,
                'print_order' => 999,
                'reduction_rate' => '0.0000',
            ],
            [
                'code' => 'unresolved_liquor',
                'name' => '酒税区分未解決',
                'taxability' => 'unknown',
                'aggregate0' => 'Alcohol',
                'aggregate1' => 'Review',
                'aggregate2' => 'Unresolved',
                'aggregate3' => null,
                'print_order' => 998,
                'reduction_rate' => '0.0000',
            ],
        ];
    }
}
