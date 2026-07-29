<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\OpeningReceivableBalance;
use App\Models\PaymentSchedule;
use App\Services\Operations\OperationalPeriod;
use Illuminate\Support\Collection;

class ReceivableBalanceService
{
    public function __construct(private readonly OperationalPeriod $operationalPeriod) {}

    public function forCustomer(Customer|int $customer): ReceivableBalance
    {
        $customer = $customer instanceof Customer
            ? $customer
            : Customer::query()->findOrFail($customer);

        $row = PaymentSchedule::query()
            ->where('customer_id', $customer->id)
            ->whereHas('invoiceHeader', function ($query): void {
                $query
                    ->whereNotIn('status', ['draft', 'cancelled'])
                    ->whereNull('cancelled_at')
                    ->whereDate('invoice_date', '>=', $this->operationalPeriod->startDate());
            })
            ->selectRaw('COALESCE(SUM(scheduled_amount), 0) as scheduled_amount')
            ->selectRaw('COALESCE(SUM(received_amount), 0) as received_amount')
            ->selectRaw('COALESCE(SUM(outstanding_amount), 0) as outstanding_amount')
            ->selectRaw("SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_schedule_count")
            ->selectRaw("SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) as partial_schedule_count")
            ->selectRaw("SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_schedule_count")
            ->first();

        $opening = OpeningReceivableBalance::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['calculated', 'reconciled'])
            ->whereDate('as_of_date', '>=', $this->operationalPeriod->startDate())
            ->orderByDesc('as_of_date')
            ->orderByDesc('id')
            ->first();

        $openingAmount = $opening !== null && ! $this->isCarriedByInvoice((int) $customer->id, $opening->as_of_date->toDateString())
            ? (string) $opening->opening_balance_amount
            : '0.00';

        return $this->balanceFromRow($customer, $row, (string) $openingAmount);
    }

    /**
     * @return Collection<int, ReceivableBalance>
     */
    public function allCustomers(): Collection
    {
        $customerIds = PaymentSchedule::query()
            ->whereHas('invoiceHeader', function ($query): void {
                $query
                    ->whereNotIn('status', ['draft', 'cancelled'])
                    ->whereNull('cancelled_at')
                    ->whereDate('invoice_date', '>=', $this->operationalPeriod->startDate());
            })
            ->pluck('customer_id')
            ->merge(OpeningReceivableBalance::query()
                ->whereIn('status', ['calculated', 'reconciled'])
                ->where('opening_balance_amount', '!=', 0)
                ->whereDate('as_of_date', '>=', $this->operationalPeriod->startDate())
                ->pluck('customer_id'))
            ->unique();

        $rows = Customer::query()
            ->whereIn('id', $customerIds)
            ->orderBy('customer_code')
            ->get()
            ->map(fn (Customer $customer): ReceivableBalance => $this->forCustomer($customer));

        return $rows;
    }

    private function balanceFromRow(Customer $customer, object $row, string $openingAmount): ReceivableBalance
    {
        $scheduledAmount = bcadd((string) $row->scheduled_amount, $openingAmount, 2);
        $outstandingAmount = bcadd((string) $row->outstanding_amount, $openingAmount, 2);
        $openingCount = bccomp($openingAmount, '0.00', 2) > 0 ? 1 : 0;

        return new ReceivableBalance(
            customerId: $customer->id,
            customerCode: $customer->customer_code,
            customerName: $customer->name,
            scheduledAmount: $scheduledAmount,
            receivedAmount: bcadd((string) $row->received_amount, '0', 2),
            outstandingAmount: $outstandingAmount,
            openScheduleCount: (int) $row->open_schedule_count + $openingCount,
            partialScheduleCount: (int) $row->partial_schedule_count,
            closedScheduleCount: (int) $row->closed_schedule_count,
        );
    }

    private function isCarriedByInvoice(int $customerId, string $openingDate): bool
    {
        return PaymentSchedule::query()
            ->where('customer_id', $customerId)
            ->whereHas('invoiceHeader', function ($query) use ($openingDate): void {
                $query
                    ->whereNotIn('status', ['draft', 'cancelled'])
                    ->whereNull('cancelled_at')
                    ->whereDate('invoice_date', '>=', $openingDate);
            })
            ->exists();
    }
}
