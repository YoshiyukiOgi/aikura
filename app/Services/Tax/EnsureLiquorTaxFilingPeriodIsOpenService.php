<?php

namespace App\Services\Tax;

use App\Exceptions\Tax\ClosedLiquorTaxFilingPeriodException;
use App\Models\LiquorTaxMonthlyFiling;

class EnsureLiquorTaxFilingPeriodIsOpenService
{
    public function ensureOpen(string $transferDate): void
    {
        $isClosed = LiquorTaxMonthlyFiling::query()
            ->whereIn('status', ['confirmed', 'closed'])
            ->whereDate('period_start', '<=', $transferDate)
            ->whereDate('period_end', '>=', $transferDate)
            ->exists();

        if ($isClosed) {
            throw ClosedLiquorTaxFilingPeriodException::forDate($transferDate);
        }
    }
}
