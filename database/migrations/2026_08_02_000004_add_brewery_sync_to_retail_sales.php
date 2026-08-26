<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection(config('retail.database.connection', 'retail'));

        $schema->table('retail_sales', function (Blueprint $table): void {
            if (! Schema::connection(config('retail.database.connection', 'retail'))->hasColumn('retail_sales', 'brewery_sales_order_id')) {
                $table->unsignedBigInteger('brewery_sales_order_id')->nullable()->after('closed_at')->index();
                $table->string('brewery_order_number', 80)->nullable()->after('brewery_sales_order_id');
                $table->unsignedBigInteger('brewery_correction_sales_order_id')->nullable()->after('brewery_order_number')->index();
                $table->string('brewery_correction_order_number', 80)->nullable()->after('brewery_correction_sales_order_id');
                $table->string('brewery_sync_status', 30)->default('not_required')->after('brewery_correction_order_number')->index();
                $table->text('brewery_sync_error')->nullable()->after('brewery_sync_status');
                $table->timestampTz('brewery_synced_at')->nullable()->after('brewery_sync_error');
            }
        });

        $schema->table('retail_sale_items', function (Blueprint $table): void {
            if (! Schema::connection(config('retail.database.connection', 'retail'))->hasColumn('retail_sale_items', 'brewery_sales_order_line_id')) {
                $table->unsignedBigInteger('brewery_sales_order_line_id')->nullable()->after('retail_product_id')->index();
            }
        });
    }

    public function down(): void
    {
        $schema = Schema::connection(config('retail.database.connection', 'retail'));

        $schema->table('retail_sale_items', function (Blueprint $table): void {
            if (Schema::connection(config('retail.database.connection', 'retail'))->hasColumn('retail_sale_items', 'brewery_sales_order_line_id')) {
                $table->dropColumn('brewery_sales_order_line_id');
            }
        });

        $schema->table('retail_sales', function (Blueprint $table): void {
            if (Schema::connection(config('retail.database.connection', 'retail'))->hasColumn('retail_sales', 'brewery_sales_order_id')) {
                $table->dropColumn([
                    'brewery_sales_order_id',
                    'brewery_order_number',
                    'brewery_correction_sales_order_id',
                    'brewery_correction_order_number',
                    'brewery_sync_status',
                    'brewery_sync_error',
                    'brewery_synced_at',
                ]);
            }
        });
    }
};
