<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection(config('retail.database.connection', 'retail'));

        $schema->table('retail_products', function (Blueprint $table): void {
            $table->string('brewery_source_status', 30)->default('current')->index();
            $table->timestampTz('brewery_source_checked_at')->nullable();
        });

        $schema->table('retail_price_sync_settings', function (Blueprint $table): void {
            $table->unsignedInteger('interval_minutes')->default(60);
            $table->json('last_detection_summary')->nullable();
            $table->text('last_detection_error')->nullable();
        });

        DB::connection(config('retail.database.connection', 'retail'))
            ->table('retail_price_sync_settings')
            ->where('detection_mode', 'auto')
            ->update(['detection_mode' => 'daily']);
    }

    public function down(): void
    {
        $schema = Schema::connection(config('retail.database.connection', 'retail'));

        $schema->table('retail_price_sync_settings', function (Blueprint $table): void {
            $table->dropColumn(['interval_minutes', 'last_detection_summary', 'last_detection_error']);
        });

        $schema->table('retail_products', function (Blueprint $table): void {
            $table->dropIndex(['brewery_source_status']);
            $table->dropColumn(['brewery_source_status', 'brewery_source_checked_at']);
        });
    }
};
