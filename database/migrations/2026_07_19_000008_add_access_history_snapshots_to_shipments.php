<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_headers', function (Blueprint $table): void {
            $table->string('legacy_access_document_number', 100)->nullable()->unique();
            $table->decimal('legacy_access_net_amount', 18, 2)->nullable();
            $table->decimal('legacy_access_consumption_tax_amount', 18, 2)->nullable();
            $table->decimal('legacy_access_total_amount', 18, 2)->nullable();
        });

        Schema::table('shipment_lines', function (Blueprint $table): void {
            $table->string('legacy_access_line_id', 100)->nullable()->unique();
            $table->string('legacy_access_detail_id', 100)->nullable()->index();
            $table->decimal('legacy_access_transaction_amount', 18, 2)->nullable();
            $table->decimal('legacy_access_consumption_tax_amount', 18, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shipment_lines', function (Blueprint $table): void {
            $table->dropColumn([
                'legacy_access_line_id', 'legacy_access_detail_id',
                'legacy_access_transaction_amount', 'legacy_access_consumption_tax_amount',
            ]);
        });

        Schema::table('shipment_headers', function (Blueprint $table): void {
            $table->dropColumn([
                'legacy_access_document_number', 'legacy_access_net_amount',
                'legacy_access_consumption_tax_amount', 'legacy_access_total_amount',
            ]);
        });
    }
};
