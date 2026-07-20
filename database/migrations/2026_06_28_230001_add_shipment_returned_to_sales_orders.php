<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->timestampTz('shipment_returned_at')->nullable()->after('awaiting_shipment_instruction');
            $table->text('shipment_returned_reason')->nullable()->after('shipment_returned_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropColumn(['shipment_returned_at', 'shipment_returned_reason']);
        });
    }
};
