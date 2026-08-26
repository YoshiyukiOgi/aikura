<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('retail.database.connection', 'retail'))
            ->table('retail_company_settings', function (Blueprint $table): void {
                $table->string('inventory_sales_policy', 30)->default('strict_stock')->after('sale_mode');
            });
    }

    public function down(): void
    {
        Schema::connection(config('retail.database.connection', 'retail'))
            ->table('retail_company_settings', function (Blueprint $table): void {
                $table->dropColumn('inventory_sales_policy');
            });
    }
};
