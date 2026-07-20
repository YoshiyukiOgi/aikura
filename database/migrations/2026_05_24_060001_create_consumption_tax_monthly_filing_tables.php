<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumption_tax_monthly_filings', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 40)->default('draft')->index();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total_taxable_amount', 18, 2)->default(0);
            $table->decimal('total_tax_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->unsignedInteger('invoice_count')->default(0);
            $table->unsignedInteger('line_count')->default(0);
            $table->timestampTz('calculated_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->text('reason')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->unique(['year', 'month']);
        });

        Schema::create('consumption_tax_monthly_filing_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('consumption_tax_monthly_filing_id');
            $table->unsignedInteger('line_no');
            $table->foreignId('consumption_tax_category_id')->constrained()->restrictOnDelete();
            $table->string('consumption_tax_category_code', 80);
            $table->string('consumption_tax_category_name', 120);
            $table->string('consumption_taxability', 40);
            $table->foreignId('consumption_tax_rate_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('tax_rate', 8, 4)->nullable();
            $table->date('consumption_tax_rate_effective_from')->nullable();
            $table->decimal('taxable_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->unsignedInteger('invoice_count')->default(0);
            $table->unsignedInteger('line_count')->default(0);
            $table->timestampsTz();

            $table->foreign('consumption_tax_monthly_filing_id', 'ct_monthly_filing_lines_filing_id_fk')
                ->references('id')
                ->on('consumption_tax_monthly_filings')
                ->cascadeOnDelete();
            $table->unique(['consumption_tax_monthly_filing_id', 'line_no'], 'ct_monthly_filing_lines_filing_line_unique');
            $table->index('consumption_tax_category_id');
            $table->index('consumption_tax_rate_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumption_tax_monthly_filing_lines');
        Schema::dropIfExists('consumption_tax_monthly_filings');
    }
};
