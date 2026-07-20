<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_headers', function (Blueprint $table): void {
            $table->foreignId('source_shipment_pick_id')
                ->nullable()
                ->after('source_shipment_header_id')
                ->constrained('shipment_picks')
                ->restrictOnDelete();
            $table->unique('source_shipment_pick_id', 'shipment_headers_source_pick_unique');
        });

        Schema::table('shipment_lines', function (Blueprint $table): void {
            $table->foreignId('source_shipment_pick_line_id')
                ->nullable()
                ->after('unit_id')
                ->constrained('shipment_pick_lines')
                ->restrictOnDelete();
            $table->unique('source_shipment_pick_line_id', 'shipment_lines_source_pick_line_unique');
        });
    }

    public function down(): void
    {
        Schema::table('shipment_lines', function (Blueprint $table): void {
            $table->dropUnique('shipment_lines_source_pick_line_unique');
            $table->dropConstrainedForeignId('source_shipment_pick_line_id');
        });

        Schema::table('shipment_headers', function (Blueprint $table): void {
            $table->dropUnique('shipment_headers_source_pick_unique');
            $table->dropConstrainedForeignId('source_shipment_pick_id');
        });
    }
};
