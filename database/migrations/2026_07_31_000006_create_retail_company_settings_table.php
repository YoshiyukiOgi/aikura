<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('retail.database.connection', 'retail'))
            ->create('retail_company_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('company_key', 80)->unique();
                $table->string('sale_mode', 30)->default('mixed');
                $table->string('delivery_note_policy', 30)->default('on_demand');
                $table->string('invoice_policy', 30)->default('monthly_credit');
                $table->string('brewery_procurement_policy', 30)->default('auto_order');
                $table->string('external_procurement_policy', 30)->default('supplier_order');
                $table->timestampsTz();
            });
    }

    public function down(): void
    {
        Schema::connection(config('retail.database.connection', 'retail'))
            ->dropIfExists('retail_company_settings');
    }
};
