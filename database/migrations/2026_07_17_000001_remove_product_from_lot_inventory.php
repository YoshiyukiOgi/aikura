<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('product_production_lot');
        Schema::dropIfExists('shipment_stock_reservations');
        Schema::dropIfExists('stock_monthly_balances');

        Schema::table('production_lots', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_id');
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_id');
        });

        Schema::table('non_sales_stock_operation_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_id');
            $table->dropConstrainedForeignId('liquor_tax_category_id');
            $table->dropConstrainedForeignId('liquor_tax_rule_id');
            $table->dropColumn([
                'liquor_tax_category_code', 'liquor_tax_category_name', 'liquor_taxability',
                'liquor_tax_calculation_method', 'liquor_taxable_kl', 'liquor_tax_per_kl',
                'liquor_tax_reduction_rate', 'liquor_tax_estimated_amount',
            ]);
        });

        Schema::table('non_sales_stock_operation_headers', function (Blueprint $table): void {
            $table->dropColumn(['consumption_tax_treatment', 'liquor_tax_treatment']);
        });

        Schema::table('inventory_count_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_id');
        });

        Schema::table('stock_lot_monthly_balances', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('production_lots', function (Blueprint $table): void {
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('non_sales_stock_operation_lines', function (Blueprint $table): void {
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('liquor_tax_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('liquor_tax_category_code', 80)->nullable();
            $table->string('liquor_tax_category_name', 120)->nullable();
            $table->string('liquor_taxability', 40)->nullable();
            $table->foreignId('liquor_tax_rule_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('liquor_tax_calculation_method', 40)->nullable();
            $table->decimal('liquor_taxable_kl', 18, 6)->nullable();
            $table->decimal('liquor_tax_per_kl', 18, 4)->nullable();
            $table->decimal('liquor_tax_reduction_rate', 8, 4)->nullable();
            $table->decimal('liquor_tax_estimated_amount', 18, 2)->nullable();
        });
        Schema::table('non_sales_stock_operation_headers', function (Blueprint $table): void {
            $table->string('consumption_tax_treatment', 40)->default('out_of_scope');
            $table->string('liquor_tax_treatment', 40)->default('not_applicable');
        });
        Schema::table('inventory_count_lines', function (Blueprint $table): void {
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('stock_lot_monthly_balances', function (Blueprint $table): void {
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
        });

        Schema::create('product_production_lot', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('production_lot_id')->constrained()->restrictOnDelete();
            $table->string('usage_type', 40)->default('shipment_candidate');
            $table->unsignedInteger('priority')->default(100);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('reason')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();
            $table->unique(['product_id', 'production_lot_id', 'usage_type']);
        });

        Schema::create('stock_monthly_balances', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 40)->default('draft');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('opening_quantity', 18, 4)->default(0);
            $table->decimal('inbound_quantity', 18, 4)->default(0);
            $table->decimal('outbound_quantity', 18, 4)->default(0);
            $table->decimal('adjustment_quantity', 18, 4)->default(0);
            $table->decimal('closing_quantity', 18, 4)->default(0);
            $table->timestampTz('calculated_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->text('reason')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();
            $table->unique(['year', 'month', 'product_id', 'stock_location_id', 'unit_id']);
        });

        Schema::create('shipment_stock_reservations', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 40)->default('reserved')->index();
            $table->foreignId('shipment_header_id')->constrained('shipment_headers')->cascadeOnDelete();
            $table->foreignId('shipment_line_id')->constrained('shipment_lines')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->timestampTz('reserved_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->text('reason')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();
        });
    }
};
