<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquor_tax_monthly_filing_lines', function (Blueprint $table): void {
            $table->decimal('cumulative_gross_before', 18, 2)->nullable();
            $table->decimal('cumulative_gross_after', 18, 2)->nullable();
            $table->json('relief_calculation_basis')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('liquor_tax_monthly_filing_lines', function (Blueprint $table): void {
            $table->dropColumn(['cumulative_gross_before', 'cumulative_gross_after', 'relief_calculation_basis']);
        });
    }
};
