<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('retail.database.connection', 'retail'))
            ->table('retail_customers', function (Blueprint $table): void {
                $table->string('billing_method', 30)->nullable()->index();
            });
    }

    public function down(): void
    {
        Schema::connection(config('retail.database.connection', 'retail'))
            ->table('retail_customers', function (Blueprint $table): void {
                $table->dropIndex(['billing_method']);
                $table->dropColumn('billing_method');
            });
    }
};
