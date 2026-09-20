<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\InternalBalanceOpening;
use App\Models\InternalMonthlyBalance;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RecordInternalBalanceOpeningService
{
    public function record(Customer $customer, string $asOfDate, string $amount, string $sourceNote): InternalBalanceOpening
    {
        $customer->loadMissing('settlementReceivableCategory');
        if ($customer->settlementReceivableCategory?->receivable_method !== 'internal_balance') {
            throw new RuntimeException("取引先 {$customer->customer_code} は社内残高区分ではありません。");
        }
        if (InternalMonthlyBalance::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['confirmed', 'closed'])
            ->whereDate('period_end', '>=', $asOfDate)
            ->exists()) {
            throw new RuntimeException("取引先 {$customer->customer_code} は社内月次残高が確定済みのため、開始残高を変更できません。");
        }

        return DB::transaction(fn (): InternalBalanceOpening => InternalBalanceOpening::query()->updateOrCreate(
            ['customer_id' => $customer->id, 'as_of_date' => $asOfDate],
            ['opening_balance_amount' => $amount, 'status' => 'reconciled', 'source_note' => $sourceNote],
        )->refresh());
    }
}
