<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_instruction_lines', function (Blueprint $table): void {
            $table->decimal('picked_quantity', 18, 4)->default('0.0000')->after('quantity');
        });

        Schema::create('shipment_picks', function (Blueprint $table): void {
            $table->id();
            $table->string('pick_number', 80)->unique();
            $table->string('status', 40)->default('picked')->index();
            $table->foreignId('shipment_instruction_id')->constrained()->restrictOnDelete();
            $table->date('pick_date');
            $table->foreignId('stock_location_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('cancelled_reason')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->index('pick_date');
            $table->index(['shipment_instruction_id', 'pick_date']);
        });

        Schema::create('shipment_pick_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_pick_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignId('shipment_instruction_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->unique(['shipment_pick_id', 'line_no']);
            $table->index('shipment_instruction_line_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_pick_lines');
        Schema::dropIfExists('shipment_picks');

        Schema::table('shipment_instruction_lines', function (Blueprint $table): void {
            $table->dropColumn('picked_quantity');
        });
    }
};
