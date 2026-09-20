<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\InternalMonthlyBalance;
use App\Models\InvoiceHeader;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreateInternalMonthlyBalanceDraftService
{
    public function __construct(private readonly InternalBalanceService $internalBalanceService) {}

    /** @return Collection<int, InternalMonthlyBalance> */
    public function create(int $year, int $month, ?string $reason = null): Collection
    {
        $start = CarbonImmutable::create($year, $month, 1);
        $end = $start->endOfMonth();

        return DB::transaction(function () use ($year, $month, $start, $end, $reason): Collection {
            $existing = InternalMonthlyBalance::query()->where('year', $year)->where('month', $month)->lockForUpdate()->get();
            if ($existing->contains(fn (InternalMonthlyBalance $row): bool => $row->status !== 'draft')) {
                throw new \RuntimeException("{$year}年{$month}月の社内残高は既に確定されています。");
            }

            $customers = Customer::query()->with('settlementReceivableCategory')->where('is_active', true)->get()
                ->filter(fn (Customer $customer): bool => $this->internalBalanceService->isInternal($customer));
            $rows = collect();
            foreach ($customers as $customer) {
                $opening = $this->internalBalanceService->balanceBefore($customer, $start->toDateString());
                $charge = bcadd((string) InvoiceHeader::query()
                    ->where('customer_id', $customer->id)
                    ->where('document_type', 'internal_statement')
                    ->whereNotIn('status', ['draft', 'cancelled'])
                    ->whereNull('cancelled_at')
                    ->whereBetween('invoice_date', [$start->toDateString(), $end->toDateString()])
                    ->sum('current_invoice_amount'), '0', 2);
                $settlement = $this->internalBalanceService->periodSettlementAmount($customer, $start->toDateString(), $end->toDateString());
                $closing = bcsub(bcadd($opening, $charge, 2), $settlement, 2);
                if (bccomp($opening, '0.00', 2) === 0 && bccomp($charge, '0.00', 2) === 0 && bccomp($settlement, '0.00', 2) === 0) {
                    continue;
                }
                $rows->push(InternalMonthlyBalance::updateOrCreate(
                    ['year' => $year, 'month' => $month, 'customer_id' => $customer->id],
                    [
                        'status' => 'draft', 'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(),
                        'customer_code' => $customer->customer_code, 'customer_name' => $customer->name,
                        'opening_amount' => $opening, 'charge_amount' => $charge, 'settlement_amount' => $settlement, 'closing_amount' => $closing,
                        'calculated_at' => now(), 'confirmed_at' => null, 'closed_at' => null, 'reason' => $reason,
                    ],
                )->refresh());
            }

            InternalMonthlyBalance::query()->where('year', $year)->where('month', $month)->where('status', 'draft')
                ->when($rows->isNotEmpty(), fn ($query) => $query->whereNotIn('customer_id', $rows->pluck('customer_id')))->delete();

            return $rows->sortBy('customer_code')->values();
        });
    }
}
