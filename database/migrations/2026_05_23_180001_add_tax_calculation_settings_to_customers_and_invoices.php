<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('tax_calculation_unit', 30)->default('line')->after('tax_rounding_method');
        });

        Schema::table('invoice_headers', function (Blueprint $table): void {
            $table->string('tax_calculation_unit', 30)->nullable()->after('due_date');
            $table->string('tax_rounding_method', 30)->nullable()->after('tax_calculation_unit');
            $table->string('amount_rounding_method', 30)->nullable()->after('tax_rounding_method');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_headers', function (Blueprint $table): void {
            $table->dropColumn(['tax_calculation_unit', 'tax_rounding_method', 'amount_rounding_method']);
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('tax_calculation_unit');
        });
    }
};
