<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_return_headers', function (Blueprint $table): void {
            $table->id();
            $table->string('return_number', 80)->unique();
            $table->string('status', 40)->default('draft')->index();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('return_date');
            $table->string('settlement_method', 40)->default('credit_memo');
            $table->foreignId('credit_invoice_header_id')->nullable()->constrained('invoice_headers')->restrictOnDelete();
            $table->timestampTz('credited_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->text('reason');
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->index(['customer_id', 'return_date']);
            $table->index('settlement_method');
        });

        Schema::create('sales_return_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_return_header_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_invoice_line_id')->constrained('invoice_lines')->restrictOnDelete();
            $table->foreignId('source_shipment_header_id')->constrained('shipment_headers')->restrictOnDelete();
            $table->foreignId('source_shipment_line_id')->constrained('shipment_lines')->restrictOnDelete();
            $table->foreignId('credit_invoice_line_id')->nullable()->constrained('invoice_lines')->restrictOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_code', 80);
            $table->string('product_name', 160);
            $table->string('display_name', 160);
            $table->decimal('quantity', 18, 4);
            $table->string('unit_code', 50);
            $table->string('unit_name', 80);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('amount', 18, 2);
            $table->foreignId('consumption_tax_category_id')->nullable()->constrained('consumption_tax_categories')->restrictOnDelete();
            $table->string('consumption_tax_category_code', 80)->nullable();
            $table->string('consumption_tax_category_name', 120)->nullable();
            $table->string('consumption_taxability', 40)->nullable();
            $table->foreignId('consumption_tax_rate_id')->nullable()->constrained('consumption_tax_rates')->restrictOnDelete();
            $table->decimal('tax_rate', 10, 4)->nullable();
            $table->date('consumption_tax_rate_effective_from')->nullable();
            $table->decimal('tax_amount', 18, 2);
            $table->decimal('total_amount', 18, 2);
            $table->string('stock_action', 40)->default('return_dedicated_stock');
            $table->foreignId('stock_location_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('production_lot_id')->nullable()->constrained('production_lots')->restrictOnDelete();
            $table->string('lot_code', 80)->nullable();
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->unique(['sales_return_header_id', 'line_no']);
            $table->index('source_invoice_line_id');
            $table->index('source_shipment_line_id');
            $table->index('stock_action');
        });

        Schema::table('invoice_headers', function (Blueprint $table): void {
            $table->string('document_type', 40)->default('invoice')->after('status')->index();
            $table->foreignId('source_sales_return_header_id')->nullable()->after('billing_cycle_id')->constrained('sales_return_headers')->restrictOnDelete();
        });

        Schema::table('invoice_lines', function (Blueprint $table): void {
            $table->foreignId('source_invoice_line_id')->nullable()->after('shipment_line_id')->constrained('invoice_lines')->restrictOnDelete();
            $table->foreignId('source_sales_return_line_id')->nullable()->after('source_invoice_line_id')->constrained('sales_return_lines')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_sales_return_line_id');
            $table->dropConstrainedForeignId('source_invoice_line_id');
        });

        Schema::table('invoice_headers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_sales_return_header_id');
            $table->dropColumn('document_type');
        });

        Schema::dropIfExists('sales_return_lines');
        Schema::dropIfExists('sales_return_headers');
    }
};
