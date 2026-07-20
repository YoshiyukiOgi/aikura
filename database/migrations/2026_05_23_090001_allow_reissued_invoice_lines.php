<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table): void {
            $table->dropUnique('invoice_lines_shipment_line_id_unique');
            $table->index('shipment_line_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table): void {
            $table->dropIndex('invoice_lines_shipment_line_id_index');
            $table->unique('shipment_line_id');
        });
    }
};
