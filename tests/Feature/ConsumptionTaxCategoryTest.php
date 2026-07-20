<?php

namespace Tests\Feature;

use App\Models\ConsumptionTaxCategory;
use Database\Seeders\TaxMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConsumptionTaxCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumption_tax_category_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('consumption_tax_categories'));

        foreach ([
            'code',
            'name',
            'taxability',
            'requires_tax_rate',
            'is_reduced_rate',
            'is_export_exempt',
            'is_invoice_display_target',
            'sort_order',
            'is_active',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('consumption_tax_categories', $column),
                "Column [consumption_tax_categories.{$column}] does not exist.",
            );
        }
    }

    public function test_tax_master_seed_creates_default_consumption_tax_categories(): void
    {
        $this->seed(TaxMasterSeeder::class);

        foreach ([
            'taxable_standard',
            'taxable_reduced',
            'non_taxable',
            'out_of_scope',
            'tax_exempt',
            'export_exempt',
        ] as $code) {
            $this->assertDatabaseHas('consumption_tax_categories', [
                'code' => $code,
                'is_active' => true,
            ]);
        }
    }

    public function test_tax_category_flags_identify_reduced_and_export_exempt_categories(): void
    {
        $this->seed(TaxMasterSeeder::class);

        $standard = ConsumptionTaxCategory::where('code', 'taxable_standard')->firstOrFail();
        $reduced = ConsumptionTaxCategory::where('code', 'taxable_reduced')->firstOrFail();
        $export = ConsumptionTaxCategory::where('code', 'export_exempt')->firstOrFail();
        $outOfScope = ConsumptionTaxCategory::where('code', 'out_of_scope')->firstOrFail();

        $this->assertSame('taxable', $standard->taxability);
        $this->assertTrue($standard->requires_tax_rate);
        $this->assertFalse($standard->is_reduced_rate);

        $this->assertSame('taxable', $reduced->taxability);
        $this->assertTrue($reduced->requires_tax_rate);
        $this->assertTrue($reduced->is_reduced_rate);

        $this->assertSame('exempt', $export->taxability);
        $this->assertTrue($export->is_export_exempt);
        $this->assertFalse($export->requires_tax_rate);

        $this->assertSame('out_of_scope', $outOfScope->taxability);
        $this->assertFalse($outOfScope->is_invoice_display_target);
    }
}
