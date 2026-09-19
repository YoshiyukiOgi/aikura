<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\InvoiceHeader;
use App\Models\OpeningReceivableBalance;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class MonthlyBillingTargetService
{
    public function __construct(
        private readonly BillableShipmentQuery $billableShipmentQuery,
        private readonly ReceivableBalanceService $receivableBalanceService,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forMonth(int $year, int $month): Collection
    {
        return Customer::query()
            ->with('billingCycle')
            ->whereHas('billingCycle', fn ($query) => $query->where('billing_method', 'monthly_closing'))
            ->where('is_active', true)
            ->where('invoice_required', true)
            ->orderBy('customer_code')
            ->get()
            ->map(function (Customer $customer) use ($year, $month): ?array {
                $period = $this->periodFor($customer, $year, $month);

                if ($this->alreadyInvoiced($customer, $period['end'])) {
                    return null;
                }

                $shipmentCount = $this->billableShipmentQuery
                    ->query($customer->id, $period['start'], $period['end'])
                    ->count();
                $hasReceivableActivity = $this->hasReceivableActivity($customer, $period['start'], $period['end']);

                if ($shipmentCount === 0 && ! $hasReceivableActivity) {
                    return null;
                }

                return [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->billing_name ?: $customer->name,
                    'billing_cycle_name' => $customer->billingCycle?->name,
                    'closing_day' => $customer->billingCycle?->closing_day,
                    'period_start' => $period['start'],
                    'period_end' => $period['end'],
                    'closing_date' => $period['end'],
                    'shipment_count' => $shipmentCount,
                    'previous_balance_amount' => $this->receivableBalanceService->forCustomer($customer)->outstandingAmount,
                    'has_receivable_activity' => $hasReceivableActivity,
                ];
            })
            ->filter()
            ->values();
    }

    /** @return array{start: string, end: string} */
    private function periodFor(Customer $customer, int $year, int $month): array
    {
        $monthStart = CarbonImmutable::create($year, $month, 1);
        $closingDay = $customer->billingCycle?->closing_day;
        $periodEnd = $closingDay === null || $closingDay >= $monthStart->daysInMonth
            ? $monthStart->endOfMonth()
            : $monthStart->day($closingDay);
        $periodStart = $closingDay === null || $closingDay >= $monthStart->daysInMonth
            ? $monthStart
            : $monthStart->subMonthNoOverflow()->day(min($closingDay + 1, $monthStart->subMonthNoOverflow()->daysInMonth));

        return ['start' => $periodStart->toDateString(), 'end' => $periodEnd->toDateString()];
    }

    private function alreadyInvoiced(Customer $customer, string $periodEnd): bool
    {
        return InvoiceHeader::query()
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', ['cancelled'])
            ->whereNull('cancelled_at')
            ->whereDate('billing_period_end', $periodEnd)
            ->exists();
    }

    private function hasReceivableActivity(Customer $customer, string $periodStart, string $periodEnd): bool
    {
        return PaymentSchedule::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['open', 'partial'])
            ->where('outstanding_amount', '>', 0)
            ->exists()
            || OpeningReceivableBalance::query()
                ->where('customer_id', $customer->id)
                ->whereIn('status', ['calculated', 'reconciled'])
                ->whereDate('as_of_date', '<=', $periodStart)
                ->where('opening_balance_amount', '!=', 0)
                ->exists()
            || Payment::query()
                ->where('customer_id', $customer->id)
                ->whereNull('cancelled_at')
                ->whereBetween('payment_date', [$periodStart, $periodEnd])
                ->exists();
    }
}
