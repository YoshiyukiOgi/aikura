<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
        });

        Schema::create('settlement_receivable_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 120);
            $table->string('receivable_method', 80)->default('accounts_receivable');
            $table->string('export_type', 80)->default('domestic');
            $table->string('liquor_tax_type', 80)->default('taxable');
            $table->string('consumption_tax_type', 80)->default('taxable');
            $table->string('accounting_code', 80)->nullable();
            $table->string('sub_accounting_code', 80)->nullable();
            $table->string('department_code', 80)->nullable();
            $table->boolean('invoice_required')->default(true);
            $table->boolean('reduces_stock')->default(true);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
        });

        Schema::create('billing_cycles', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 120);
            $table->unsignedTinyInteger('closing_day')->nullable();
            $table->smallInteger('payment_month_offset')->default(1);
            $table->unsignedTinyInteger('payment_day')->nullable();
            $table->string('billing_method', 80)->default('monthly_closing');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();

            $table->index('billing_method');
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('customer_code', 80)->unique();
            $table->string('name', 160);
            $table->string('name_kana', 160)->nullable();
            $table->string('short_name', 120)->nullable();
            $table->string('billing_name', 160)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('address1', 255)->nullable();
            $table->string('address2', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('fax', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('contact_name', 120)->nullable();
            $table->foreignId('transaction_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('settlement_receivable_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('billing_cycle_id')->constrained()->restrictOnDelete();
            $table->string('tax_rounding_method', 30)->default('round');
            $table->string('amount_rounding_method', 30)->default('round');
            $table->boolean('invoice_required')->default(true);
            $table->text('search_key')->nullable();
            $table->string('legacy_code', 80)->nullable()->index();
            $table->string('legacy_name', 160)->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampTz('disabled_at')->nullable();
            $table->timestampsTz();

            $table->index('name');
            $table->index('name_kana');
            $table->index('short_name');
            $table->index('billing_cycle_id');
            $table->index('transaction_category_id');
            $table->index('settlement_receivable_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
        Schema::dropIfExists('billing_cycles');
        Schema::dropIfExists('settlement_receivable_categories');
        Schema::dropIfExists('transaction_categories');
    }
};

