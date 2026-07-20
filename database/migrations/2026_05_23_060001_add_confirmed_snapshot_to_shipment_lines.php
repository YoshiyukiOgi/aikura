<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_lines', function (Blueprint $table): void {
            $table->string('confirmed_product_code', 80)->nullable()->after('draft_priced_at');
            $table->string('confirmed_product_name', 160)->nullable()->after('confirmed_product_code');
            $table->string('confirmed_display_name', 160)->nullable()->after('confirmed_product_name');
            $table->string('confirmed_product_type', 50)->nullable()->after('confirmed_display_name');
            $table->string('confirmed_unit_code', 50)->nullable()->after('confirmed_product_type');
            $table->string('confirmed_unit_name', 80)->nullable()->after('confirmed_unit_code');
            $table->decimal('confirmed_quantity', 18, 4)->nullable()->after('confirmed_unit_name');
            $table->decimal('confirmed_unit_price', 18, 4)->nullable()->after('confirmed_quantity');
            $table->foreignId('confirmed_price_list_id')->nullable()->after('confirmed_unit_price')->constrained('price_lists')->restrictOnDelete();
            $table->foreignId('confirmed_price_rule_id')->nullable()->after('confirmed_price_list_id')->constrained('price_rules')->restrictOnDelete();
            $table->string('confirmed_price_source', 80)->nullable()->after('confirmed_price_rule_id');
            $table->text('confirmed_price_reason')->nullable()->after('confirmed_price_source');
            $table->decimal('confirmed_capacity_value', 18, 4)->nullable()->after('confirmed_price_reason');
            $table->foreignId('confirmed_capacity_unit_id')->nullable()->after('confirmed_capacity_value')->constrained('units')->restrictOnDelete();
            $table->decimal('confirmed_alcohol_percentage', 5, 2)->nullable()->after('confirmed_capacity_unit_id');
            $table->string('confirmed_rounding_method', 30)->nullable()->after('confirmed_alcohol_percentage');
            $table->timestampTz('confirmed_at')->nullable()->after('confirmed_rounding_method');
        });
    }

    public function down(): void
    {
        Schema::table('shipment_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('confirmed_capacity_unit_id');
            $table->dropConstrainedForeignId('confirmed_price_rule_id');
            $table->dropConstrainedForeignId('confirmed_price_list_id');
            $table->dropColumn([
                'confirmed_product_code',
                'confirmed_product_name',
                'confirmed_display_name',
                'confirmed_product_type',
                'confirmed_unit_code',
                'confirmed_unit_name',
                'confirmed_quantity',
                'confirmed_unit_price',
                'confirmed_price_source',
                'confirmed_price_reason',
                'confirmed_capacity_value',
                'confirmed_alcohol_percentage',
                'confirmed_rounding_method',
                'confirmed_at',
            ]);
        });
    }
};

