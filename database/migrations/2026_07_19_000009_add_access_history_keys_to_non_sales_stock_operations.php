<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('non_sales_stock_operation_headers', function (Blueprint $table): void {
            $table->string('legacy_access_stock_operation_id', 100)->nullable()->unique();
            $table->decimal('legacy_access_volume_delta_ml', 18, 4)->nullable();
        });

        Schema::table('non_sales_stock_operation_lines', function (Blueprint $table): void {
            $table->string('legacy_access_stock_leg_key', 120)->nullable()->unique();
            $table->string('legacy_access_detail_id', 100)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('non_sales_stock_operation_lines', function (Blueprint $table): void {
            $table->dropColumn(['legacy_access_stock_leg_key', 'legacy_access_detail_id']);
        });

        Schema::table('non_sales_stock_operation_headers', function (Blueprint $table): void {
            $table->dropColumn(['legacy_access_stock_operation_id', 'legacy_access_volume_delta_ml']);
        });
    }
};
