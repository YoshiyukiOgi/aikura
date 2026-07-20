<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_lines', function (Blueprint $table): void {
            $table->foreignId('confirmed_liquor_tax_category_id')->nullable()->after('confirmed_consumption_tax_rate_effective_from')->constrained('liquor_tax_categories')->restrictOnDelete();
            $table->string('confirmed_liquor_tax_category_code', 80)->nullable()->after('confirmed_liquor_tax_category_id');
            $table->string('confirmed_liquor_tax_category_name', 120)->nullable()->after('confirmed_liquor_tax_category_code');
            $table->string('confirmed_liquor_taxability', 40)->nullable()->after('confirmed_liquor_tax_category_name');
            $table->foreignId('confirmed_liquor_tax_rule_id')->nullable()->after('confirmed_liquor_taxability')->constrained('liquor_tax_rules')->restrictOnDelete();
            $table->string('confirmed_liquor_tax_calculation_method', 80)->nullable()->after('confirmed_liquor_tax_rule_id');
            $table->decimal('confirmed_liquor_taxable_kl', 18, 6)->nullable()->after('confirmed_liquor_tax_calculation_method');
            $table->decimal('confirmed_liquor_tax_per_kl', 18, 4)->nullable()->after('confirmed_liquor_taxable_kl');
            $table->decimal('confirmed_liquor_tax_reduction_rate', 8, 4)->nullable()->after('confirmed_liquor_tax_per_kl');
            $table->decimal('confirmed_liquor_tax_amount', 18, 2)->nullable()->after('confirmed_liquor_tax_reduction_rate');
        });
    }

    public function down(): void
    {
        Schema::table('shipment_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('confirmed_liquor_tax_rule_id');
            $table->dropConstrainedForeignId('confirmed_liquor_tax_category_id');
            $table->dropColumn([
                'confirmed_liquor_tax_category_code',
                'confirmed_liquor_tax_category_name',
                'confirmed_liquor_taxability',
                'confirmed_liquor_tax_calculation_method',
                'confirmed_liquor_taxable_kl',
                'confirmed_liquor_tax_per_kl',
                'confirmed_liquor_tax_reduction_rate',
                'confirmed_liquor_tax_amount',
            ]);
        });
    }
};
