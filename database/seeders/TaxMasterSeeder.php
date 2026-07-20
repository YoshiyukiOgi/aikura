<?php

namespace Database\Seeders;

use App\Models\ConsumptionTaxCategory;
use App\Models\ConsumptionTaxRate;
use Illuminate\Database\Seeder;

class TaxMasterSeeder extends Seeder
{
    public function run(): void
    {
        ConsumptionTaxCategory::updateOrCreate(
            ['code' => 'taxable_standard'],
            [
                'name' => '課税 標準税率',
                'taxability' => 'taxable',
                'requires_tax_rate' => true,
                'is_reduced_rate' => false,
                'is_export_exempt' => false,
                'is_invoice_display_target' => true,
                'sort_order' => 10,
                'is_active' => true,
            ],
        );

        ConsumptionTaxCategory::updateOrCreate(
            ['code' => 'taxable_reduced'],
            [
                'name' => '課税 軽減税率',
                'taxability' => 'taxable',
                'requires_tax_rate' => true,
                'is_reduced_rate' => true,
                'is_export_exempt' => false,
                'is_invoice_display_target' => true,
                'sort_order' => 20,
                'is_active' => true,
            ],
        );

        ConsumptionTaxCategory::updateOrCreate(
            ['code' => 'non_taxable'],
            [
                'name' => '非課税',
                'taxability' => 'non_taxable',
                'requires_tax_rate' => false,
                'is_reduced_rate' => false,
                'is_export_exempt' => false,
                'is_invoice_display_target' => true,
                'sort_order' => 30,
                'is_active' => true,
            ],
        );

        ConsumptionTaxCategory::updateOrCreate(
            ['code' => 'out_of_scope'],
            [
                'name' => '不課税',
                'taxability' => 'out_of_scope',
                'requires_tax_rate' => false,
                'is_reduced_rate' => false,
                'is_export_exempt' => false,
                'is_invoice_display_target' => false,
                'sort_order' => 40,
                'is_active' => true,
            ],
        );

        ConsumptionTaxCategory::updateOrCreate(
            ['code' => 'tax_exempt'],
            [
                'name' => '免税',
                'taxability' => 'exempt',
                'requires_tax_rate' => false,
                'is_reduced_rate' => false,
                'is_export_exempt' => false,
                'is_invoice_display_target' => true,
                'sort_order' => 50,
                'is_active' => true,
            ],
        );

        ConsumptionTaxCategory::updateOrCreate(
            ['code' => 'export_exempt'],
            [
                'name' => '輸出免税',
                'taxability' => 'exempt',
                'requires_tax_rate' => false,
                'is_reduced_rate' => false,
                'is_export_exempt' => true,
                'is_invoice_display_target' => true,
                'sort_order' => 60,
                'is_active' => true,
            ],
        );

        $standard = ConsumptionTaxCategory::where('code', 'taxable_standard')->firstOrFail();
        $reduced = ConsumptionTaxCategory::where('code', 'taxable_reduced')->firstOrFail();

        ConsumptionTaxRate::updateOrCreate(
            [
                'consumption_tax_category_id' => $standard->id,
                'effective_from' => '2014-04-01',
            ],
            [
                'name' => '標準税率 8%',
                'rate' => '0.0800',
                'effective_to' => '2019-09-30',
                'is_active' => true,
            ],
        );

        ConsumptionTaxRate::updateOrCreate(
            [
                'consumption_tax_category_id' => $standard->id,
                'effective_from' => '2019-10-01',
            ],
            [
                'name' => '標準税率 10%',
                'rate' => '0.1000',
                'effective_to' => null,
                'is_active' => true,
            ],
        );

        ConsumptionTaxRate::updateOrCreate(
            [
                'consumption_tax_category_id' => $reduced->id,
                'effective_from' => '2019-10-01',
            ],
            [
                'name' => '軽減税率 8%',
                'rate' => '0.0800',
                'effective_to' => null,
                'is_active' => true,
            ],
        );
    }
}
