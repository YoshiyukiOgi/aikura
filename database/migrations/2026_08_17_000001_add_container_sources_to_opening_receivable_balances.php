<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opening_receivable_balances', function (Blueprint $table): void {
            $table->unsignedInteger('source_container_entry_count')->default(0)->after('source_ledger_entry_count');
            $table->decimal('source_container_amount', 18, 2)->default(0)->after('source_ledger_amount');
        });
    }

    public function down(): void
    {
        Schema::table('opening_receivable_balances', function (Blueprint $table): void {
            $table->dropColumn(['source_container_entry_count', 'source_container_amount']);
        });
    }
};
