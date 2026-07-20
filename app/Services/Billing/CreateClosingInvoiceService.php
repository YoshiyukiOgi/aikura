<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\InvoiceHeader;
use Illuminate\Support\Carbon;

class CreateClosingInvoiceService
{
    public function __construct(
        private readonly CreateInvoiceDraftService $createInvoiceDraftService,
    ) {
    }

    public function create(
        int $customerId,
        string $closingDate,
        ?string $dueDate = null,
        ?string $note = null,
        ?string $reason = null,
    ): InvoiceHeader {
        $customer = Customer::query()
            ->with('billingCycle')
            ->findOrFail($customerId);

        $periodEnd = Carbon::parse($closingDate)->toDateString();
        $periodStart = $this->periodStart($customer, Carbon::parse($periodEnd));

        return $this->createInvoiceDraftService->create(new CreateInvoiceDraftData(
            customerId: $customer->id,
            invoiceDate: $periodEnd,
            billingPeriodStart: $periodStart,
            billingPeriodEnd: $periodEnd,
            dueDate: $dueDate,
            note: $note,
            reason: $reason,
            shipmentHeaderIds: null,
            includeCarriedForward: true,
        ));
    }

    private function periodStart(Customer $customer, Carbon $periodEnd): string
    {
        $lastPeriodEnd = InvoiceHeader::query()
            ->where('customer_id', $customer->id)
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('billing_period_end')
            ->whereDate('billing_period_end', '<', $periodEnd->toDateString())
            ->max('billing_period_end');

        if ($lastPeriodEnd !== null) {
            return Carbon::parse($lastPeriodEnd)->addDay()->toDateString();
        }

        $closingDay = $customer->billingCycle?->closing_day;

        if ($closingDay === null || $closingDay >= $periodEnd->daysInMonth) {
            return $periodEnd->copy()->startOfMonth()->toDateString();
        }

        return $periodEnd->copy()
            ->subMonthNoOverflow()
            ->day(min($closingDay + 1, $periodEnd->copy()->subMonthNoOverflow()->daysInMonth))
            ->toDateString();
    }
}
