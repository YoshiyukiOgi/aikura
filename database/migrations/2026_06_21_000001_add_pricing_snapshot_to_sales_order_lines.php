<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table): void {
            $table->decimal('unit_price', 18, 4)->nullable();
            $table->foreignId('price_list_id')->nullable()->constrained('price_lists')->restrictOnDelete();
            $table->foreignId('price_rule_id')->nullable()->constrained('price_rules')->restrictOnDelete();
            $table->string('price_source', 80)->nullable();
            $table->text('price_reason')->nullable();
            $table->timestampTz('priced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('price_rule_id');
            $table->dropConstrainedForeignId('price_list_id');
            $table->dropColumn(['unit_price', 'price_source', 'price_reason', 'priced_at']);
        });
    }
};
