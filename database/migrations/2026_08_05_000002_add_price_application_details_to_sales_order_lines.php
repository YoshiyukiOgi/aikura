<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table): void {
            $table->date('price_effective_from')->nullable()->after('price_reason');
            $table->decimal('previous_unit_price', 18, 4)->nullable()->after('price_effective_from');
            $table->string('price_change_notice', 255)->nullable()->after('previous_unit_price');
        });

        DB::statement(<<<'SQL'
            UPDATE sales_order_lines AS lines
            SET price_effective_from = rules.effective_from
            FROM price_rules AS rules
            WHERE rules.id = lines.price_rule_id
              AND lines.price_effective_from IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table): void {
            $table->dropColumn(['price_effective_from', 'previous_unit_price', 'price_change_notice']);
        });
    }
};
