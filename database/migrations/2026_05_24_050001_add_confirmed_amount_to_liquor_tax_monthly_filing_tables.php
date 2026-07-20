<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquor_tax_monthly_filings', function (Blueprint $table): void {
            $table->decimal('total_confirmed_amount', 18, 2)->nullable()->after('total_estimated_amount');
        });

        Schema::table('liquor_tax_monthly_filing_lines', function (Blueprint $table): void {
            $table->decimal('confirmed_amount', 18, 2)->nullable()->after('estimated_amount');
        });
    }

    public function down(): void
    {
        Schema::table('liquor_tax_monthly_filing_lines', function (Blueprint $table): void {
            $table->dropColumn('confirmed_amount');
        });

        Schema::table('liquor_tax_monthly_filings', function (Blueprint $table): void {
            $table->dropColumn('total_confirmed_amount');
        });
    }
};
