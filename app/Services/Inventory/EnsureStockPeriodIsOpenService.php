<?php

namespace App\Services\Inventory;

use App\Exceptions\Inventory\ClosedStockPeriodException;
use App\Models\StockLotMonthlyBalance;

class EnsureStockPeriodIsOpenService
{
    public function isOpen(string $movementDate): bool
    {
        return ! StockLotMonthlyBalance::query()
            ->whereIn('status', ['confirmed', 'closed'])
            ->whereDate('period_start', '<=', $movementDate)
            ->whereDate('period_end', '>=', $movementDate)
            ->exists();
    }

    public function ensureOpen(string $movementDate): void
    {
        if (! $this->isOpen($movementDate)) {
            throw ClosedStockPeriodException::forDate($movementDate);
        }
    }
}
