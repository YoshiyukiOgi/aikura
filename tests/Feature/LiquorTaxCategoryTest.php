<?php

namespace Tests\Feature;

use App\Models\LiquorTaxCategory;
use Database\Seeders\LiquorTaxMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LiquorTaxCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_liquor_tax_categories_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('liquor_tax_categories'));

        foreach ([
            'code',
            'name',
            'taxability',
            'aggregate0',
            'aggregate1',
            'aggregate2',
            'aggregate3',
            'print_order',
            'reduction_rate',
            'description',
            'is_active',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('liquor_tax_categories', $column),
                "Column [liquor_tax_categories.{$column}] does not exist.",
            );
        }
    }

    public function test_liquor_tax_master_seed_creates_default_categories(): void
    {
        $this->seed(LiquorTaxMasterSeeder::class);

        foreach ([
            'seishu',
            'liqueur',
            'other_brewed_liquor',
            'spirits',
            'mirin',
            'beer',
            'export_exempt_liquor',
            'non_liquor',
        ] as $code) {
            $this->assertDatabaseHas('liquor_tax_categories', [
                'code' => $code,
                'is_active' => true,
            ]);
        }
    }

    public function test_liquor_tax_category_has_aggregation_and_reduction_rate_fields(): void
    {
        $this->seed(LiquorTaxMasterSeeder::class);

        $seishu = LiquorTaxCategory::where('code', 'seishu')->firstOrFail();
        $export = LiquorTaxCategory::where('code', 'export_exempt_liquor')->firstOrFail();
        $nonLiquor = LiquorTaxCategory::where('code', 'non_liquor')->firstOrFail();

        $this->assertSame('taxable', $seishu->taxability);
        $this->assertSame('Alcohol', $seishu->aggregate0);
        $this->assertSame('Brewed liquor', $seishu->aggregate1);
        $this->assertSame('Seishu', $seishu->aggregate2);
        $this->assertSame('0.0000', $seishu->reduction_rate);

        $this->assertSame('exempt', $export->taxability);
        $this->assertSame('out_of_scope', $nonLiquor->taxability);
    }
}
