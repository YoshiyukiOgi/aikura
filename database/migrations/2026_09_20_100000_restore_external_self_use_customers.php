<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $accountsReceivableId = DB::table('settlement_receivable_categories')
            ->where('code', 'accounts_receivable_1')
            ->value('id');

        if ($accountsReceivableId === null) {
            return;
        }

        DB::table('customers')
            ->whereIn('customer_code', ['ITARO-C-0033', 'ITARO-C-0099'])
            ->update([
                'settlement_receivable_category_id' => $accountsReceivableId,
                'invoice_required' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $internalBalanceId = DB::table('settlement_receivable_categories')
            ->where('code', 'internal_balance')
            ->value('id');

        if ($internalBalanceId === null) {
            return;
        }

        DB::table('customers')
            ->whereIn('customer_code', ['ITARO-C-0033', 'ITARO-C-0099'])
            ->update(['settlement_receivable_category_id' => $internalBalanceId, 'updated_at' => now()]);
    }
};
