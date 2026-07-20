<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_lines', function (Blueprint $table): void {
            $table->decimal('draft_unit_price', 18, 4)->nullable()->after('unit_id');
            $table->foreignId('draft_price_list_id')->nullable()->after('draft_unit_price')->constrained('price_lists')->restrictOnDelete();
            $table->foreignId('draft_price_rule_id')->nullable()->after('draft_price_list_id')->constrained('price_rules')->restrictOnDelete();
            $table->string('draft_price_source', 80)->nullable()->after('draft_price_rule_id');
            $table->text('draft_price_reason')->nullable()->after('draft_price_source');
            $table->timestampTz('draft_priced_at')->nullable()->after('draft_price_reason');
        });
    }

    public function down(): void
    {
        Schema::table('shipment_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('draft_price_rule_id');
            $table->dropConstrainedForeignId('draft_price_list_id');
            $table->dropColumn([
                'draft_unit_price',
                'draft_price_source',
                'draft_price_reason',
                'draft_priced_at',
            ]);
        });
    }
};
