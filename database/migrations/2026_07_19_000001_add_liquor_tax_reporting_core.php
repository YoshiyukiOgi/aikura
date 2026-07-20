<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlement_receivable_categories', function (Blueprint $table): void {
            $table->boolean('requires_tax_review')->default(false);
            $table->boolean('requires_evidence')->default(false);
        });

        Schema::table('shipment_headers', function (Blueprint $table): void {
            $table->foreignId('confirmed_settlement_receivable_category_id')->nullable()
                ->constrained('settlement_receivable_categories')->restrictOnDelete();
            $table->string('confirmed_settlement_receivable_category_code', 80)->nullable();
            $table->string('confirmed_settlement_receivable_category_name', 120)->nullable();
            $table->string('confirmed_liquor_tax_treatment', 40)->nullable()->index();
            $table->string('confirmed_consumption_tax_treatment', 40)->nullable();
            $table->string('confirmed_export_type', 40)->nullable();
            $table->string('confirmed_receivable_method', 40)->nullable();
            $table->boolean('confirmed_invoice_required')->nullable();
            $table->boolean('confirmed_requires_tax_review')->default(false);
            $table->boolean('confirmed_requires_evidence')->default(false);
        });

        Schema::table('non_sales_stock_operation_headers', function (Blueprint $table): void {
            $table->string('liquor_tax_treatment', 40)->default('not_applicable')->index();
            $table->boolean('requires_tax_review')->default(false);
        });

        Schema::table('non_sales_stock_operation_lines', function (Blueprint $table): void {
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
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

        Schema::create('liquor_tax_relief_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('manufacturing_site_code', 80)->default('main');
            $table->string('scheme', 40)->default('legacy_scheme')->index();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('legacy_reduction_rate', 8, 4)->default('0.2000');
            $table->decimal('legacy_annual_quantity_limit_kl', 18, 6)->default('200.000000');
            $table->decimal('opening_eligible_quantity_kl', 18, 6)->default(0);
            $table->decimal('opening_gross_tax_amount', 18, 2)->default(0);
            $table->decimal('prior_year_peak_taxable_quantity_kl', 18, 6)->nullable();
            $table->date('selection_notice_date')->nullable();
            $table->date('discontinuance_notice_date')->nullable();
            $table->string('calculation_rule_version', 80);
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();

            $table->unique(['manufacturing_site_code', 'effective_from'], 'liquor_tax_relief_site_effective_unique');
            $table->index(['manufacturing_site_code', 'effective_from', 'effective_to'], 'liquor_tax_relief_site_period_index');
        });

        Schema::table('liquor_tax_monthly_filings', function (Blueprint $table): void {
            $table->string('manufacturing_site_code', 80)->default('main');
            $table->unsignedSmallInteger('fiscal_year')->nullable();
            $table->foreignId('liquor_tax_relief_setting_id')->nullable()
                ->constrained('liquor_tax_relief_settings')->restrictOnDelete();
            $table->string('relief_scheme', 40)->nullable();
            $table->string('calculation_rule_version', 80)->nullable();
            $table->decimal('total_gross_tax_amount', 18, 2)->default(0);
            $table->decimal('total_relief_amount', 18, 2)->default(0);
            $table->decimal('total_deduction_amount', 18, 2)->default(0);
            $table->decimal('net_payable_amount', 18, 2)->default(0);
            $table->unsignedInteger('warning_count')->default(0);
        });

        Schema::table('liquor_tax_monthly_filing_lines', function (Blueprint $table): void {
            $table->string('tax_treatment', 40)->default('taxable')->index();
            $table->string('source_type', 40)->default('shipment')->index();
            $table->decimal('gross_tax_amount', 18, 2)->default(0);
            $table->decimal('relief_eligible_kl', 18, 6)->default(0);
            $table->decimal('relief_amount', 18, 2)->default(0);
            $table->decimal('deduction_amount', 18, 2)->default(0);
            $table->decimal('net_tax_amount', 18, 2)->default(0);
            $table->boolean('requires_review')->default(false);
        });

        Schema::create('liquor_tax_monthly_filing_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('liquor_tax_monthly_filing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('liquor_tax_monthly_filing_line_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 40)->index();
            $table->unsignedBigInteger('source_header_id');
            $table->unsignedBigInteger('source_line_id');
            $table->string('source_document_number', 80)->nullable();
            $table->date('source_date');
            $table->string('tax_treatment', 40)->index();
            $table->decimal('quantity', 18, 4);
            $table->decimal('taxable_kl', 18, 6)->default(0);
            $table->decimal('gross_tax_amount', 18, 2)->default(0);
            $table->boolean('requires_review')->default(false);
            $table->text('review_reason')->nullable();
            $table->timestampsTz();

            $table->unique(['source_type', 'source_header_id', 'source_line_id'], 'liquor_tax_filing_source_unique');
            $table->index(['liquor_tax_monthly_filing_id', 'source_date'], 'liquor_tax_filing_source_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquor_tax_monthly_filing_sources');

        Schema::table('liquor_tax_monthly_filing_lines', function (Blueprint $table): void {
            $table->dropColumn([
                'tax_treatment', 'source_type', 'gross_tax_amount', 'relief_eligible_kl',
                'relief_amount', 'deduction_amount', 'net_tax_amount', 'requires_review',
            ]);
        });

        Schema::table('liquor_tax_monthly_filings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('liquor_tax_relief_setting_id');
            $table->dropColumn([
                'manufacturing_site_code', 'fiscal_year', 'relief_scheme', 'calculation_rule_version',
                'total_gross_tax_amount', 'total_relief_amount', 'total_deduction_amount',
                'net_payable_amount', 'warning_count',
            ]);
        });

        Schema::dropIfExists('liquor_tax_relief_settings');

        Schema::table('non_sales_stock_operation_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_id');
            $table->dropConstrainedForeignId('liquor_tax_category_id');
            $table->dropConstrainedForeignId('liquor_tax_rule_id');
            $table->dropColumn([
                'liquor_tax_category_code', 'liquor_tax_category_name', 'liquor_taxability',
                'liquor_tax_calculation_method', 'liquor_taxable_kl', 'liquor_tax_per_kl',
                'liquor_tax_reduction_rate', 'liquor_tax_estimated_amount',
            ]);
        });

        Schema::table('non_sales_stock_operation_headers', function (Blueprint $table): void {
            $table->dropColumn(['liquor_tax_treatment', 'requires_tax_review']);
        });

        Schema::table('shipment_headers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('confirmed_settlement_receivable_category_id');
            $table->dropColumn([
                'confirmed_settlement_receivable_category_code', 'confirmed_settlement_receivable_category_name',
                'confirmed_liquor_tax_treatment', 'confirmed_consumption_tax_treatment',
                'confirmed_export_type', 'confirmed_receivable_method', 'confirmed_invoice_required',
                'confirmed_requires_tax_review', 'confirmed_requires_evidence',
            ]);
        });

        Schema::table('settlement_receivable_categories', function (Blueprint $table): void {
            $table->dropColumn(['requires_tax_review', 'requires_evidence']);
        });
    }
};
