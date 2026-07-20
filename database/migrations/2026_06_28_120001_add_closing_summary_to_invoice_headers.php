<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_headers', function (Blueprint $table): void {
            $table->decimal('previous_balance_amount', 18, 2)->default(0)->after('due_date');
            $table->decimal('period_payment_amount', 18, 2)->default(0)->after('previous_balance_amount');
            $table->decimal('carried_forward_amount', 18, 2)->default(0)->after('period_payment_amount');
            $table->decimal('current_sales_amount', 18, 2)->default(0)->after('carried_forward_amount');
            $table->decimal('current_tax_amount', 18, 2)->default(0)->after('current_sales_amount');
            $table->decimal('current_invoice_amount', 18, 2)->default(0)->after('current_tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_headers', function (Blueprint $table): void {
            $table->dropColumn([
                'previous_balance_amount',
                'period_payment_amount',
                'carried_forward_amount',
                'current_sales_amount',
                'current_tax_amount',
                'current_invoice_amount',
            ]);
        });
    }
};
