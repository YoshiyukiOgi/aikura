<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_balance_openings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('as_of_date');
            $table->decimal('opening_balance_amount', 18, 2)->default(0);
            $table->string('status', 40)->default('reconciled')->index();
            $table->text('source_note')->nullable();
            $table->timestampsTz();

            $table->unique(['customer_id', 'as_of_date']);
            $table->index(['customer_id', 'as_of_date']);
        });

        Schema::create('internal_balance_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('settlement_date');
            $table->decimal('amount', 18, 2);
            $table->string('reason', 255);
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->index(['customer_id', 'settlement_date']);
        });

        Schema::create('internal_monthly_balances', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 40)->default('draft')->index();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('customer_code', 80);
            $table->string('customer_name', 255);
            $table->decimal('opening_amount', 18, 2)->default(0);
            $table->decimal('charge_amount', 18, 2)->default(0);
            $table->decimal('settlement_amount', 18, 2)->default(0);
            $table->decimal('closing_amount', 18, 2)->default(0);
            $table->timestampTz('calculated_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->text('reason')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->unique(['year', 'month', 'customer_id']);
            $table->index(['customer_id', 'period_end']);
        });

        DB::table('settlement_receivable_categories')->updateOrInsert(
            ['code' => 'internal_balance'],
            [
                'name' => '社内残高',
                'receivable_method' => 'internal_balance',
                'export_type' => 'domestic',
                'liquor_tax_type' => 'taxable',
                'consumption_tax_type' => 'non_taxable',
                'invoice_required' => true,
                'reduces_stock' => true,
                'requires_tax_review' => true,
                'requires_evidence' => false,
                'description' => '社内請求・社内残高として管理する。外部売掛、入金予定、入金消込の対象にはしない。',
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $internalCategoryId = DB::table('settlement_receivable_categories')->where('code', 'internal_balance')->value('id');
        $legacyCategoryId = DB::table('settlement_receivable_categories')->where('code', 'self_consumption')->value('id');
        if ($internalCategoryId !== null && $legacyCategoryId !== null) {
            DB::table('customers')
                ->where('settlement_receivable_category_id', $legacyCategoryId)
                ->update([
                    'settlement_receivable_category_id' => $internalCategoryId,
                    'invoice_required' => true,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_monthly_balances');
        Schema::dropIfExists('internal_balance_settlements');
        Schema::dropIfExists('internal_balance_openings');
    }
};
