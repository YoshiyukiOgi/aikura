<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('non_sales_stock_operation_lines', function (Blueprint $table): void {
            $table->foreignId('liquor_tax_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('liquor_tax_category_code', 80)->nullable();
            $table->string('liquor_tax_category_name', 120)->nullable();
            $table->string('liquor_taxability', 40)->nullable();
            $table->foreignId('liquor_tax_rule_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('liquor_tax_calculation_method', 40)->nullable();
            $table->decimal('liquor_taxable_kl', 18, 6)->nullable();
            $table->decimal('liquor_tax_per_kl', 18, 4)->nullable();
            $table->decimal('liquor_tax_reduction_rate', 8, 4)->nullable();
            $table->decimal('liquor_tax_estimated_amount', 18, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('non_sales_stock_operation_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('liquor_tax_category_id');
            $table->dropConstrainedForeignId('liquor_tax_rule_id');
            $table->dropColumn(['liquor_tax_category_code', 'liquor_tax_category_name', 'liquor_taxability', 'liquor_tax_calculation_method', 'liquor_taxable_kl', 'liquor_tax_per_kl', 'liquor_tax_reduction_rate', 'liquor_tax_estimated_amount']);
        });
    }
};
