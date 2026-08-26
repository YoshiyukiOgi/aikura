<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection(config('retail.database.connection', 'retail'));

        $schema->create('retail_price_sync_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('detection_mode', 20)->default('manual');
            $table->timestampTz('last_detected_at')->nullable();
            $table->timestampsTz();
        });

        $schema->create('retail_price_change_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('retail_product_id')->constrained('retail_products')->cascadeOnDelete();
            $table->unsignedBigInteger('brewery_product_id')->index();
            $table->decimal('current_cost_price', 14, 2);
            $table->decimal('source_cost_price', 14, 2);
            $table->decimal('current_selling_price', 14, 2);
            $table->decimal('source_selling_price', 14, 2);
            $table->string('status', 20)->default('open')->index();
            $table->timestampTz('detected_at')->index();
            $table->timestampTz('applied_at')->nullable();
            $table->string('applied_mode', 30)->nullable();
            $table->timestampsTz();

            $table->index(['retail_product_id', 'status'], 'retail_price_candidates_product_status_index');
        });

        $schema->create('retail_price_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('retail_product_id')->constrained('retail_products')->cascadeOnDelete();
            $table->foreignId('retail_price_change_candidate_id')->nullable()->constrained('retail_price_change_candidates')->nullOnDelete();
            $table->decimal('old_cost_price', 14, 2);
            $table->decimal('new_cost_price', 14, 2);
            $table->decimal('old_selling_price', 14, 2);
            $table->decimal('new_selling_price', 14, 2);
            $table->string('apply_mode', 30);
            $table->timestampTz('applied_at')->index();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection(config('retail.database.connection', 'retail'));

        $schema->dropIfExists('retail_price_histories');
        $schema->dropIfExists('retail_price_change_candidates');
        $schema->dropIfExists('retail_price_sync_settings');
    }
};
