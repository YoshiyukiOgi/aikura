<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('retail')->create('retail_ledger_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retail_company_id')->constrained('retail_companies')->restrictOnDelete();
            $table->foreignId('retail_customer_id')->constrained('retail_customers')->restrictOnDelete();
            $table->string('document_no', 64);
            $table->date('business_date');
            $table->string('source', 32)->default('manual');
            $table->string('source_document_id', 128)->nullable();
            $table->string('source_hash', 64)->nullable();
            $table->string('status', 16)->default('active');
            $table->json('source_payload')->nullable();
            $table->timestamps();

            $table->unique(['retail_company_id', 'document_no']);
            $table->unique(['retail_company_id', 'source', 'source_document_id']);
            $table->index(['retail_company_id', 'business_date']);
            $table->index(['retail_customer_id', 'business_date']);
        });

        Schema::connection('retail')->create('retail_ledger_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retail_ledger_document_id')->constrained('retail_ledger_documents')->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->string('line_kind', 16);
            $table->foreignId('retail_product_id')->nullable()->constrained('retail_products')->restrictOnDelete();
            $table->integer('source_product_id')->nullable();
            $table->string('description')->nullable();
            $table->decimal('quantity', 14, 3)->nullable();
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('source_line_id', 128)->nullable();
            $table->json('source_payload')->nullable();
            $table->timestamps();

            $table->unique(['retail_ledger_document_id', 'line_no']);
            $table->index(['line_kind', 'source_product_id']);
        });

        Schema::connection('retail')->create('retail_monthly_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retail_company_id')->constrained('retail_companies')->restrictOnDelete();
            $table->date('period');
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('document_count')->default(0);
            $table->unsignedInteger('line_count')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['retail_company_id', 'period']);
        });

        Schema::connection('retail')->create('retail_monthly_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retail_monthly_closing_id')->constrained('retail_monthly_closings')->cascadeOnDelete();
            $table->foreignId('retail_customer_id')->constrained('retail_customers')->restrictOnDelete();
            $table->decimal('opening_amount', 14, 2)->default(0);
            $table->decimal('sales_amount', 14, 2)->default(0);
            $table->decimal('payment_amount', 14, 2)->default(0);
            $table->decimal('adjustment_amount', 14, 2)->default(0);
            $table->decimal('closing_amount', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['retail_monthly_closing_id', 'retail_customer_id'], 'retail_monthly_balance_customer_unique');
        });

        Schema::connection('retail')->create('retail_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retail_company_id')->constrained('retail_companies')->restrictOnDelete();
            $table->string('source', 32)->default('tamagawa_access');
            $table->date('period_from');
            $table->date('period_to');
            $table->string('source_file_name')->nullable();
            $table->string('source_file_hash', 64);
            $table->string('status', 16)->default('imported');
            $table->unsignedInteger('document_count')->default(0);
            $table->unsignedInteger('line_count')->default(0);
            $table->decimal('sales_total', 14, 2)->default(0);
            $table->decimal('payment_total', 14, 2)->default(0);
            $table->json('source_payload')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['retail_company_id', 'source', 'source_file_hash'], 'retail_import_batch_source_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('retail')->dropIfExists('retail_import_batches');
        Schema::connection('retail')->dropIfExists('retail_monthly_balances');
        Schema::connection('retail')->dropIfExists('retail_monthly_closings');
        Schema::connection('retail')->dropIfExists('retail_ledger_lines');
        Schema::connection('retail')->dropIfExists('retail_ledger_documents');
    }
};
