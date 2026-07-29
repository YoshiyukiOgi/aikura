<?php

namespace App\Services\Billing;

use App\Exceptions\Billing\PaymentScheduleException;
use App\Models\InvoiceHeader;
use App\Models\PaymentSchedule;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreatePaymentScheduleService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly EnsureReceivableMonthlyBalancePeriodIsOpenService $ensureReceivableMonthlyBalancePeriodIsOpenService,
    ) {
    }

    public function create(InvoiceHeader $invoice, ?string $reason = null): PaymentSchedule
    {
        return DB::transaction(function () use ($invoice, $reason): PaymentSchedule {
            $invoice = InvoiceHeader::query()
                ->with(['customer.billingCycle'])
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            if ($invoice->status !== 'confirmed') {
                throw PaymentScheduleException::invoiceNotConfirmed($invoice->id, $invoice->status);
            }

            $existingSchedule = PaymentSchedule::query()
                ->where('invoice_header_id', $invoice->id)
                ->first();

            if ($existingSchedule !== null) {
                return $existingSchedule->load(['invoiceHeader', 'customer']);
            }

            $this->ensureReceivableMonthlyBalancePeriodIsOpenService
                ->ensureOpen($invoice->invoice_date->toDateString());

            $expectedPaymentDate = $invoice->due_date
                ?? $this->calculateExpectedPaymentDate($invoice);

            $this->closeCarriedForwardSchedules($invoice, $reason);

            $schedule = PaymentSchedule::create([
                'invoice_header_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'status' => 'open',
                'expected_payment_date' => $expectedPaymentDate,
                'scheduled_amount' => $invoice->total_amount,
                'received_amount' => '0.00',
                'outstanding_amount' => $invoice->total_amount,
            ]);

            $this->auditLogService->record(new AuditLogData(
                event: 'payment_schedule.created',
                auditable: $schedule,
                afterValues: [
                    'invoice_header_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'expected_payment_date' => $schedule->expected_payment_date?->toDateString(),
                    'scheduled_amount' => $schedule->scheduled_amount,
                    'outstanding_amount' => $schedule->outstanding_amount,
                ],
                reason: $reason,
            ));

            return $schedule->refresh()->load(['invoiceHeader', 'customer']);
        });
    }

    private function closeCarriedForwardSchedules(InvoiceHeader $invoice, ?string $reason = null): void
    {
        if (bccomp((string) $invoice->carried_forward_amount, '0.00', 2) <= 0) {
            return;
        }

        $schedules = PaymentSchedule::query()
            ->where('customer_id', $invoice->customer_id)
            ->where('invoice_header_id', '!=', $invoice->id)
            ->whereHas('invoiceHeader', function ($query): void {
                $query
                    ->whereNotIn('status', ['draft', 'cancelled'])
                    ->whereNull('cancelled_at');
            })
            ->whereIn('status', ['open', 'partial'])
            ->where('outstanding_amount', '>', 0)
            ->lockForUpdate()
            ->get();

        foreach ($schedules as $schedule) {
            $before = [
                'status' => $schedule->status,
                'outstanding_amount' => $schedule->outstanding_amount,
                'note' => $schedule->note,
            ];

            $schedule->forceFill([
                'status' => 'closed',
                'outstanding_amount' => '0.00',
                'closed_at' => now(),
                'note' => trim((string) $schedule->note . "\n締め請求 {$invoice->invoice_number} へ繰越"),
            ])->save();

            $this->auditLogService->record(new AuditLogData(
                event: 'payment_schedule.carried_forward',
                auditable: $schedule,
                beforeValues: $before,
                afterValues: [
                    'status' => $schedule->status,
                    'outstanding_amount' => $schedule->outstanding_amount,
                    'carried_to_invoice_id' => $invoice->id,
                    'carried_to_invoice_number' => $invoice->invoice_number,
                ],
                reason: $reason,
            ));
        }
    }

    private function calculateExpectedPaymentDate(InvoiceHeader $invoice): Carbon
    {
        $billingCycle = $invoice->customer->billingCycle;
        $baseDate = $invoice->invoice_date instanceof Carbon
            ? $invoice->invoice_date->copy()
            : Carbon::parse($invoice->invoice_date);

        $paymentDate = $baseDate->addMonthsNoOverflow($billingCycle->payment_month_offset);

        if ($billingCycle->payment_day === null) {
            return $paymentDate;
        }

        $day = min($billingCycle->payment_day, $paymentDate->daysInMonth);

        return $paymentDate->day($day);
    }
}
