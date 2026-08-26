<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('retail.database.connection', 'retail'))
            ->table('retail_sales', function (Blueprint $table): void {
                $table->string('status', 30)->default('posted')->index()->after('sale_type');
                $table->foreignId('original_retail_sale_id')->nullable()->after('status')->constrained('retail_sales')->nullOnDelete();
                $table->string('correction_type', 30)->nullable()->after('original_retail_sale_id');
                $table->text('correction_reason')->nullable()->after('note');
                $table->timestampTz('cancelled_at')->nullable()->after('correction_reason');
                $table->timestampTz('revised_at')->nullable()->after('cancelled_at');
                $table->timestampTz('closed_at')->nullable()->after('revised_at');
            });

        Schema::connection(config('retail.database.connection', 'retail'))
            ->create('retail_adjustment_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('retail_sale_id')->nullable()->constrained('retail_sales')->nullOnDelete();
                $table->foreignId('related_retail_sale_id')->nullable()->constrained('retail_sales')->nullOnDelete();
                $table->string('event_type', 50)->index();
                $table->string('brewery_sync_status', 30)->default('not_required')->index();
                $table->unsignedBigInteger('brewery_sales_order_id')->nullable()->index();
                $table->decimal('quantity_delta', 14, 3)->default(0);
                $table->decimal('amount_delta', 14, 2)->default(0);
                $table->text('reason')->nullable();
                $table->timestampTz('occurred_at')->index();
                $table->timestampsTz();
            });
    }

    public function down(): void
    {
        Schema::connection(config('retail.database.connection', 'retail'))->dropIfExists('retail_adjustment_events');

        Schema::connection(config('retail.database.connection', 'retail'))
            ->table('retail_sales', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('original_retail_sale_id');
                $table->dropColumn([
                    'status',
                    'correction_type',
                    'correction_reason',
                    'cancelled_at',
                    'revised_at',
                    'closed_at',
                ]);
            });
    }
};
