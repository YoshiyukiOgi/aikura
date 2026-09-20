<?php

namespace App\Services\Billing;

use App\Models\InternalMonthlyBalance;
use RuntimeException;

class EnsureInternalMonthlyBalancePeriodIsOpenService
{
    public function ensureOpen(string $date): void
    {
        if (InternalMonthlyBalance::query()
            ->whereIn('status', ['confirmed', 'closed'])
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->exists()) {
            throw new RuntimeException("社内残高の月次確定・締め済み期間のため変更できません: {$date}");
        }
    }
}
