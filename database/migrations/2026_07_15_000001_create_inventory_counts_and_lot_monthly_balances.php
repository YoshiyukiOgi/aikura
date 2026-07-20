<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_count_headers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('count_date');
            $table->string('status', 40)->default('draft')->index();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('counted_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->text('reason')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->unique(['year', 'month']);
            $table->unique('count_date');
        });

        Schema::create('inventory_count_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_count_header_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('production_lot_id')->nullable()->constrained('production_lots')->restrictOnDelete();
            $table->string('lot_code', 80)->nullable();
            $table->decimal('book_quantity', 18, 4);
            $table->decimal('counted_quantity', 18, 4)->nullable();
            $table->decimal('variance_quantity', 18, 4)->nullable();
            $table->foreignId('adjustment_stock_movement_id')->nullable()->constrained('stock_movements')->restrictOnDelete();
            $table->timestampTz('counted_at')->nullable();
            $table->text('reason')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->unique(['inventory_count_header_id', 'line_no']);
            $table->index(['inventory_count_header_id', 'product_id', 'stock_location_id'], 'inventory_count_line_lookup');
        });

        Schema::create('stock_lot_monthly_balances', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 40)->default('draft')->index();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignId('production_lot_id')->constrained('production_lots')->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('closing_quantity', 18, 4);
            $table->timestampTz('calculated_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['year', 'month', 'production_lot_id', 'product_id', 'stock_location_id', 'unit_id'], 'stock_lot_monthly_balance_unique');
            $table->index(['product_id', 'stock_location_id', 'unit_id', 'period_end'], 'stock_lot_monthly_balance_lookup');
        });

        Schema::table('non_sales_stock_operation_headers', function (Blueprint $table): void {
            $table->string('consumption_tax_treatment', 40)->default('out_of_scope')->after('operation_type');
            $table->string('liquor_tax_treatment', 40)->default('not_applicable')->after('consumption_tax_treatment');
        });
    }

    public function down(): void
    {
        Schema::table('non_sales_stock_operation_headers', function (Blueprint $table): void {
            $table->dropColumn(['consumption_tax_treatment', 'liquor_tax_treatment']);
        });
        Schema::dropIfExists('stock_lot_monthly_balances');
        Schema::dropIfExists('inventory_count_lines');
        Schema::dropIfExists('inventory_count_headers');
    }
};
