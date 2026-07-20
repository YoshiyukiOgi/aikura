<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_lines', function (Blueprint $table): void {
            $table->foreignId('confirmed_consumption_tax_category_id')->nullable()->after('confirmed_rounding_method')->constrained('consumption_tax_categories')->restrictOnDelete();
            $table->string('confirmed_consumption_tax_category_code', 80)->nullable()->after('confirmed_consumption_tax_category_id');
            $table->string('confirmed_consumption_tax_category_name', 120)->nullable()->after('confirmed_consumption_tax_category_code');
            $table->string('confirmed_consumption_taxability', 40)->nullable()->after('confirmed_consumption_tax_category_name');
            $table->foreignId('confirmed_consumption_tax_rate_id')->nullable()->after('confirmed_consumption_taxability')->constrained('consumption_tax_rates')->restrictOnDelete();
            $table->decimal('confirmed_consumption_tax_rate', 8, 4)->nullable()->after('confirmed_consumption_tax_rate_id');
            $table->date('confirmed_consumption_tax_rate_effective_from')->nullable()->after('confirmed_consumption_tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('shipment_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('confirmed_consumption_tax_rate_id');
            $table->dropConstrainedForeignId('confirmed_consumption_tax_category_id');
            $table->dropColumn([
                'confirmed_consumption_tax_category_code',
                'confirmed_consumption_tax_category_name',
                'confirmed_consumption_taxability',
                'confirmed_consumption_tax_rate',
                'confirmed_consumption_tax_rate_effective_from',
            ]);
        });
    }
};
