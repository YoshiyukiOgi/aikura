<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('retail.database.connection', 'retail'))
            ->table('retail_deliveries', function (Blueprint $table): void {
                $table->timestampTz('cancelled_at')->nullable()->after('issued_at');
                $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            });
    }

    public function down(): void
    {
        Schema::connection(config('retail.database.connection', 'retail'))
            ->table('retail_deliveries', function (Blueprint $table): void {
                $table->dropColumn(['cancelled_at', 'cancellation_reason']);
            });
    }
};
