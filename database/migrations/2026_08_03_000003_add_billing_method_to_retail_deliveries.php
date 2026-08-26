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
                $table->string('billing_method_snapshot', 30)->default('monthly');
            });
    }

    public function down(): void
    {
        Schema::connection(config('retail.database.connection', 'retail'))
            ->table('retail_deliveries', function (Blueprint $table): void {
                $table->dropColumn('billing_method_snapshot');
            });
    }
};
