<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection(config('retail.database.connection', 'retail'));

        $schema->create('retail_customers', function (Blueprint $table): void {
            $table->id();
            $table->string('customer_code', 80)->unique();
            $table->string('name', 160);
            $table->string('name_kana', 160)->nullable();
            $table->string('billing_name', 160)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('address1')->nullable();
            $table->string('address2')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->unsignedTinyInteger('closing_day')->nullable();
            $table->smallInteger('payment_month_offset')->default(1);
            $table->unsignedTinyInteger('payment_day')->nullable();
            $table->decimal('credit_limit', 14, 2)->nullable();
            $table->boolean('invoice_required')->default(false);
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();

            $table->index(['name', 'name_kana']);
        });

        $schema->create('retail_suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('supplier_code', 80)->unique();
            $table->string('name', 160);
            $table->string('supplier_type', 30)->default('external')->index();
            $table->string('ordering_method', 30)->default('manual');
            $table->unsignedBigInteger('brewery_partner_id')->nullable()->index();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('address1')->nullable();
            $table->string('address2')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
        });

        $schema->create('retail_products', function (Blueprint $table): void {
            $table->id();
            $table->string('product_code', 80)->unique();
            $table->string('name', 160);
            $table->string('name_kana', 160)->nullable();
            $table->string('procurement_source', 30)->index();
            $table->foreignId('retail_supplier_id')->constrained('retail_suppliers')->restrictOnDelete();
            $table->unsignedBigInteger('brewery_product_id')->nullable()->index();
            $table->decimal('cost_price', 14, 2)->default(0);
            $table->decimal('selling_price', 14, 2)->default(0);
            $table->decimal('tax_rate', 7, 4)->default(0.1000);
            $table->string('stock_unit', 30)->default('本');
            $table->decimal('reorder_point', 14, 3)->nullable();
            $table->decimal('reorder_quantity', 14, 3)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();

            $table->index(['procurement_source', 'retail_supplier_id']);
        });

        $schema->create('retail_sales', function (Blueprint $table): void {
            $table->id();
            $table->string('sale_no', 80)->unique();
            $table->foreignId('retail_customer_id')->nullable()->constrained('retail_customers')->nullOnDelete();
            $table->date('sale_date')->index();
            $table->string('sale_type', 30)->default('cash')->index();
            $table->string('payment_status', 30)->default('paid')->index();
            $table->decimal('subtotal_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestampsTz();
        });

        $schema->create('retail_sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('retail_sale_id')->constrained('retail_sales')->cascadeOnDelete();
            $table->foreignId('retail_product_id')->nullable()->constrained('retail_products')->nullOnDelete();
            $table->string('description', 255);
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('tax_rate', 7, 4)->default(0.1000);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_amount', 14, 2)->default(0);
            $table->timestampsTz();
        });

        $schema->create('retail_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->string('delivery_no', 80)->unique();
            $table->foreignId('retail_customer_id')->nullable()->constrained('retail_customers')->nullOnDelete();
            $table->foreignId('retail_sale_id')->nullable()->constrained('retail_sales')->nullOnDelete();
            $table->date('delivery_date')->index();
            $table->string('delivery_name', 160)->nullable();
            $table->string('delivery_postal_code', 20)->nullable();
            $table->string('delivery_address1')->nullable();
            $table->string('delivery_address2')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->timestampTz('issued_at')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();
        });

        $schema->create('retail_delivery_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('retail_delivery_id')->constrained('retail_deliveries')->cascadeOnDelete();
            $table->foreignId('retail_sale_item_id')->nullable()->constrained('retail_sale_items')->nullOnDelete();
            $table->string('description', 255);
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_amount', 14, 2)->default(0);
            $table->timestampsTz();
        });

        $schema->create('retail_invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('invoice_no', 80)->unique();
            $table->foreignId('retail_customer_id')->constrained('retail_customers')->restrictOnDelete();
            $table->date('invoice_date')->index();
            $table->date('closing_date')->index();
            $table->date('due_date')->nullable()->index();
            $table->decimal('subtotal_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('balance_amount', 14, 2)->default(0);
            $table->string('status', 30)->default('open')->index();
            $table->text('note')->nullable();
            $table->timestampsTz();
        });

        $schema->create('retail_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('retail_invoice_id')->constrained('retail_invoices')->cascadeOnDelete();
            $table->foreignId('retail_sale_id')->nullable()->constrained('retail_sales')->nullOnDelete();
            $table->string('description', 255);
            $table->decimal('quantity', 14, 3)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_amount', 14, 2)->default(0);
            $table->timestampsTz();
        });

        $schema->create('retail_payments', function (Blueprint $table): void {
            $table->id();
            $table->string('payment_no', 80)->unique();
            $table->foreignId('retail_customer_id')->constrained('retail_customers')->restrictOnDelete();
            $table->date('payment_date')->index();
            $table->string('payment_method', 30)->index();
            $table->decimal('amount', 14, 2);
            $table->decimal('unapplied_amount', 14, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestampsTz();
        });

        $schema->create('retail_payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('retail_payment_id')->constrained('retail_payments')->cascadeOnDelete();
            $table->foreignId('retail_invoice_id')->constrained('retail_invoices')->restrictOnDelete();
            $table->decimal('allocated_amount', 14, 2);
            $table->timestampsTz();

            $table->unique(['retail_payment_id', 'retail_invoice_id']);
        });

        $schema->create('retail_purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('purchase_order_no', 80)->unique();
            $table->foreignId('retail_supplier_id')->constrained('retail_suppliers')->restrictOnDelete();
            $table->string('supplier_type', 30)->index();
            $table->string('order_route', 30)->index();
            $table->string('status', 30)->default('draft')->index();
            $table->timestampTz('ordered_at')->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->decimal('subtotal_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestampsTz();
        });

        $schema->create('retail_purchase_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('retail_purchase_order_id')->constrained('retail_purchase_orders')->cascadeOnDelete();
            $table->foreignId('retail_product_id')->nullable()->constrained('retail_products')->nullOnDelete();
            $table->string('description', 255);
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_amount', 14, 2)->default(0);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection(config('retail.database.connection', 'retail'));

        $schema->dropIfExists('retail_purchase_order_lines');
        $schema->dropIfExists('retail_purchase_orders');
        $schema->dropIfExists('retail_payment_allocations');
        $schema->dropIfExists('retail_payments');
        $schema->dropIfExists('retail_invoice_lines');
        $schema->dropIfExists('retail_invoices');
        $schema->dropIfExists('retail_delivery_lines');
        $schema->dropIfExists('retail_deliveries');
        $schema->dropIfExists('retail_sale_items');
        $schema->dropIfExists('retail_sales');
        $schema->dropIfExists('retail_products');
        $schema->dropIfExists('retail_suppliers');
        $schema->dropIfExists('retail_customers');
    }
};
