<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquor_tax_monthly_filing_lines', function (Blueprint $table): void {
            $table->unsignedSmallInteger('reporting_alcohol_percentage')->nullable()->after('source_type');
        });

        Schema::table('liquor_tax_monthly_filing_sources', function (Blueprint $table): void {
            $table->unsignedSmallInteger('reporting_alcohol_percentage')->nullable()->after('tax_treatment');
        });
    }

    public function down(): void
    {
        Schema::table('liquor_tax_monthly_filing_sources', function (Blueprint $table): void {
            $table->dropColumn('reporting_alcohol_percentage');
        });

        Schema::table('liquor_tax_monthly_filing_lines', function (Blueprint $table): void {
            $table->dropColumn('reporting_alcohol_percentage');
        });
    }
};
