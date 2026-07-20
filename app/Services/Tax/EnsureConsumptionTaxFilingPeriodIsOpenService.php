<?php

namespace App\Services\Tax;

use App\Exceptions\Tax\ClosedConsumptionTaxFilingPeriodException;
use App\Models\ConsumptionTaxMonthlyFiling;

class EnsureConsumptionTaxFilingPeriodIsOpenService
{
    public function ensureOpen(string $invoiceDate): void
    {
        $isClosed = ConsumptionTaxMonthlyFiling::query()
            ->whereIn('status', ['confirmed', 'closed'])
            ->whereDate('period_start', '<=', $invoiceDate)
            ->whereDate('period_end', '>=', $invoiceDate)
            ->exists();

        if ($isClosed) {
            throw ClosedConsumptionTaxFilingPeriodException::forDate($invoiceDate);
        }
    }
}
