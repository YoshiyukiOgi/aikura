<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_headers', function (Blueprint $table): void {
            $table->foreignId('source_shipment_instruction_id')->nullable()->after('source_shipment_pick_id')->constrained('shipment_instructions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shipment_headers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_shipment_instruction_id');
        });
    }
};
