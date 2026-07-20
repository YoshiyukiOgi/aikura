<?php

namespace App\Services\Billing;

use App\Exceptions\Billing\ClosedReceivableMonthlyBalancePeriodException;
use App\Models\ReceivableMonthlyBalance;

class EnsureReceivableMonthlyBalancePeriodIsOpenService
{
    public function ensureOpen(string $date): void
    {
        $isClosed = ReceivableMonthlyBalance::query()
            ->whereIn('status', ['confirmed', 'closed'])
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->exists();

        if ($isClosed) {
            throw ClosedReceivableMonthlyBalancePeriodException::forDate($date);
        }
    }
}
