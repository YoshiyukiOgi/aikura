<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\InternalBalanceOpening;
use App\Models\InternalBalanceSettlement;
use App\Models\InvoiceHeader;

class InternalBalanceService
{
    public function isInternal(Customer $customer): bool
    {
        return $customer->settlementReceivableCategory?->receivable_method === 'internal_balance';
    }

    public function balanceBefore(Customer $customer, string $date): string
    {
        $opening = InternalBalanceOpening::query()
            ->where('customer_id', $customer->id)
            ->whereDate('as_of_date', '<=', $date)
            ->orderByDesc('as_of_date')
            ->first();

        if ($opening === null) {
            return '0.00';
        }

        $charges = InvoiceHeader::query()
            ->where('customer_id', $customer->id)
            ->where('document_type', 'internal_statement')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereNull('cancelled_at')
            ->whereDate('invoice_date', '>=', $opening->as_of_date->toDateString())
            ->whereDate('invoice_date', '<', $date)
            ->sum('current_invoice_amount');
        $settlements = InternalBalanceSettlement::query()
            ->where('customer_id', $customer->id)
            ->whereDate('settlement_date', '>=', $opening->as_of_date->toDateString())
            ->whereDate('settlement_date', '<', $date)
            ->sum('amount');

        return bcsub(
            bcadd((string) $opening->opening_balance_amount, (string) $charges, 2),
            (string) $settlements,
            2,
        );
    }

    public function periodSettlementAmount(Customer $customer, ?string $periodStart, ?string $periodEnd): string
    {
        if ($periodStart === null || $periodEnd === null) {
            return '0.00';
        }

        return bcadd((string) InternalBalanceSettlement::query()
            ->where('customer_id', $customer->id)
            ->whereBetween('settlement_date', [$periodStart, $periodEnd])
            ->sum('amount'), '0', 2);
    }

    public function hasActivity(Customer $customer, string $periodStart): bool
    {
        return bccomp($this->balanceBefore($customer, $periodStart), '0.00', 2) !== 0
            || InternalBalanceSettlement::query()
                ->where('customer_id', $customer->id)
                ->whereDate('settlement_date', '>=', $periodStart)
                ->exists();
    }
}
