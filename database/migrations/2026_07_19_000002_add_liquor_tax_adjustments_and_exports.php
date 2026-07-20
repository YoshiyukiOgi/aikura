<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquor_tax_monthly_filings', function (Blueprint $table): void {
            $table->decimal('total_adjustment_taxable_kl', 14, 6)->default(0);
            $table->decimal('total_adjustment_amount', 14, 2)->default(0);
            $table->unsignedInteger('adjustment_count')->default(0);
        });

        Schema::create('liquor_tax_adjustment_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('manufacturing_site_code', 80)->unique();
            $table->decimal('approval_amount_threshold', 14, 2)->default(0);
            $table->decimal('approval_quantity_threshold_kl', 14, 6)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('liquor_tax_monthly_filing_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('liquor_tax_monthly_filing_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->string('status', 30)->default('active')->index();
            $table->string('adjustment_type', 40);
            $table->foreignId('liquor_tax_category_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->decimal('taxable_kl_adjustment', 14, 6)->default(0);
            $table->decimal('tax_amount_adjustment', 14, 2)->default(0);
            $table->boolean('approval_required')->default(false);
            $table->foreignId('approval_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestampsTz();

            $table->unique(['liquor_tax_monthly_filing_id', 'line_no'], 'liquor_tax_filing_adjustment_line_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquor_tax_monthly_filing_adjustments');
        Schema::dropIfExists('liquor_tax_adjustment_settings');
        Schema::table('liquor_tax_monthly_filings', function (Blueprint $table): void {
            $table->dropColumn(['total_adjustment_taxable_kl', 'total_adjustment_amount', 'adjustment_count']);
        });
    }
};
