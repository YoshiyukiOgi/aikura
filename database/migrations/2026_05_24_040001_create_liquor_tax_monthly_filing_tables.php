<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquor_tax_monthly_filings', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 40)->default('draft')->index();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total_taxable_kl', 18, 6)->default(0);
            $table->decimal('total_estimated_amount', 18, 2)->default(0);
            $table->unsignedInteger('shipment_count')->default(0);
            $table->unsignedInteger('line_count')->default(0);
            $table->timestampTz('calculated_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->text('reason')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->unique(['year', 'month']);
        });

        Schema::create('liquor_tax_monthly_filing_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('liquor_tax_monthly_filing_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignId('liquor_tax_category_id')->constrained()->restrictOnDelete();
            $table->string('liquor_tax_category_code', 80);
            $table->string('liquor_tax_category_name', 120);
            $table->string('liquor_taxability', 40);
            $table->foreignId('liquor_tax_rule_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('calculation_method', 80)->nullable();
            $table->decimal('tax_per_kl', 18, 4)->nullable();
            $table->decimal('reduction_rate', 8, 4)->nullable();
            $table->decimal('taxable_kl', 18, 6)->default(0);
            $table->decimal('estimated_amount', 18, 2)->default(0);
            $table->unsignedInteger('shipment_count')->default(0);
            $table->unsignedInteger('line_count')->default(0);
            $table->timestampsTz();

            $table->unique(['liquor_tax_monthly_filing_id', 'line_no']);
            $table->index('liquor_tax_category_id');
            $table->index('liquor_tax_rule_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquor_tax_monthly_filing_lines');
        Schema::dropIfExists('liquor_tax_monthly_filings');
    }
};
