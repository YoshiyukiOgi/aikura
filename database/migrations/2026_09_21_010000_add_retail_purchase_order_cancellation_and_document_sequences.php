<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection(config('retail.database.connection', 'retail'));

        $schema->create('retail_document_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20);
            $table->date('document_date');
            $table->unsignedInteger('current_number')->default(0);
            $table->timestampsTz();

            $table->unique(['code', 'document_date']);
        });

        $schema->table('retail_purchase_orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('brewery_cancellation_sales_order_id')->nullable()->index()->after('brewery_sales_order_id');
            $table->timestampTz('cancelled_at')->nullable()->after('ordered_at');
            $table->text('cancelled_reason')->nullable()->after('cancelled_at');
            $table->string('brewery_cancel_status', 30)->default('not_required')->index()->after('brewery_api_error');
            $table->text('brewery_cancel_error')->nullable()->after('brewery_cancel_status');
            $table->timestampTz('brewery_cancelled_at')->nullable()->after('brewery_cancel_error');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection(config('retail.database.connection', 'retail'));

        $schema->table('retail_purchase_orders', function (Blueprint $table): void {
            $table->dropIndex(['brewery_cancellation_sales_order_id']);
            $table->dropIndex(['brewery_cancel_status']);
            $table->dropColumn([
                'brewery_cancellation_sales_order_id',
                'cancelled_at',
                'cancelled_reason',
                'brewery_cancel_status',
                'brewery_cancel_error',
                'brewery_cancelled_at',
            ]);
        });

        $schema->dropIfExists('retail_document_sequences');
    }
};
