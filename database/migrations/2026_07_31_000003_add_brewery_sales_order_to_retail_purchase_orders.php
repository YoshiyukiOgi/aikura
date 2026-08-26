<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('retail.database.connection', 'retail'))
            ->table('retail_purchase_orders', function (Blueprint $table): void {
                $table->unsignedBigInteger('brewery_sales_order_id')->nullable()->index()->after('order_route');
                $table->text('brewery_api_error')->nullable()->after('brewery_sales_order_id');
            });
    }

    public function down(): void
    {
        Schema::connection(config('retail.database.connection', 'retail'))
            ->table('retail_purchase_orders', function (Blueprint $table): void {
                $table->dropColumn(['brewery_sales_order_id', 'brewery_api_error']);
            });
    }
};
