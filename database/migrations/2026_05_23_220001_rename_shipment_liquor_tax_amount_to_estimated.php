<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_lines', function (Blueprint $table): void {
            $table->renameColumn('confirmed_liquor_tax_amount', 'confirmed_liquor_tax_estimated_amount');
        });
    }

    public function down(): void
    {
        Schema::table('shipment_lines', function (Blueprint $table): void {
            $table->renameColumn('confirmed_liquor_tax_estimated_amount', 'confirmed_liquor_tax_amount');
        });
    }
};
