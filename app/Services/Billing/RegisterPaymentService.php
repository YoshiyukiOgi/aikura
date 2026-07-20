<?php

namespace App\Services\Billing;

use App\Exceptions\Billing\PaymentRegistrationException;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

class RegisterPaymentService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly EnsureReceivableMonthlyBalancePeriodIsOpenService $ensureReceivableMonthlyBalancePeriodIsOpenService,
    ) {}

    public function register(
        PaymentSchedule $schedule,
        string $amount,
        string $paymentDate,
        string $paymentMethod = 'bank_transfer',
        ?string $referenceNumber = null,
        ?string $note = null,
        ?string $reason = null,
    ): Payment {
        if (bccomp($amount, '0.00', 2) <= 0) {
            throw PaymentRegistrationException::nonPositiveAmount($amount);
        }

        $this->ensureReceivableMonthlyBalancePeriodIsOpenService->ensureOpen($paymentDate);

        return DB::transaction(function () use ($schedule, $amount, $paymentDate, $paymentMethod, $referenceNumber, $note, $reason): Payment {
            $schedule = PaymentSchedule::query()
                ->with('invoiceHeader')
                ->lockForUpdate()
                ->findOrFail($schedule->id);

            if ($schedule->status === 'closed') {
                throw PaymentRegistrationException::scheduleClosed($schedule->id);
            }

            $allocatedAmount = bccomp($amount, $schedule->outstanding_amount, 2) === 1
                ? $schedule->outstanding_amount
                : $amount;
            $unappliedAmount = bcsub($amount, $allocatedAmount, 2);

            $payment = Payment::create([
                'customer_id' => $schedule->customer_id,
                'status' => bccomp($unappliedAmount, '0.00', 2) === 1 ? 'review_required' : 'allocated',
                'payment_date' => $paymentDate,
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'unapplied_amount' => $unappliedAmount,
                'reference_number' => $referenceNumber,
                'note' => $note,
            ]);

            $payment->allocations()->create([
                'payment_schedule_id' => $schedule->id,
                'invoice_header_id' => $schedule->invoice_header_id,
                'allocated_amount' => $allocatedAmount,
                'note' => $note,
            ]);

            $receivedAmount = bcadd($schedule->received_amount, $allocatedAmount, 2);
            $outstandingAmount = bcsub($schedule->scheduled_amount, $receivedAmount, 2);
            $status = bccomp($outstandingAmount, '0.00', 2) === 0 ? 'closed' : 'partial';

            $schedule->forceFill([
                'received_amount' => $receivedAmount,
                'outstanding_amount' => $outstandingAmount,
                'status' => $status,
                'closed_at' => $status === 'closed' ? now() : null,
            ])->save();

            $this->auditLogService->record(new AuditLogData(
                event: 'payment.registered',
                auditable: $payment,
                afterValues: [
                    'payment_schedule_id' => $schedule->id,
                    'invoice_header_id' => $schedule->invoice_header_id,
                    'payment_date' => $payment->payment_date?->toDateString(),
                    'payment_method' => $payment->payment_method,
                    'amount' => $payment->amount,
                    'allocated_amount' => $allocatedAmount,
                    'unapplied_amount' => $payment->unapplied_amount,
                    'schedule_status' => $schedule->status,
                    'schedule_outstanding_amount' => $schedule->outstanding_amount,
                ],
                reason: $reason,
            ));

            return $payment->refresh()->load(['customer', 'allocations']);
        });
    }

    public function registerForCustomer(
        int $customerId,
        string $amount,
        string $paymentDate,
        string $paymentMethod = 'bank_transfer',
        ?string $referenceNumber = null,
        ?string $note = null,
        ?string $reason = null,
    ): Payment {
        if (bccomp($amount, '0.00', 2) <= 0) {
            throw PaymentRegistrationException::nonPositiveAmount($amount);
        }

        $this->ensureReceivableMonthlyBalancePeriodIsOpenService->ensureOpen($paymentDate);

        return DB::transaction(function () use ($customerId, $amount, $paymentDate, $paymentMethod, $referenceNumber, $note, $reason): Payment {
            $schedules = PaymentSchedule::query()
                ->with('invoiceHeader')
                ->where('customer_id', $customerId)
                ->whereIn('status', ['open', 'partial'])
                ->where('outstanding_amount', '>', 0)
                ->orderBy('expected_payment_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $remaining = $amount;
            $totalAllocated = '0.00';

            $payment = Payment::create([
                'customer_id' => $customerId,
                'status' => 'allocated',
                'payment_date' => $paymentDate,
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'unapplied_amount' => '0.00',
                'reference_number' => $referenceNumber,
                'note' => $note,
            ]);

            foreach ($schedules as $schedule) {
                if (bccomp($remaining, '0.00', 2) <= 0) {
                    break;
                }

                $allocatedAmount = bccomp($remaining, $schedule->outstanding_amount, 2) === 1
                    ? $schedule->outstanding_amount
                    : $remaining;

                if (bccomp($allocatedAmount, '0.00', 2) <= 0) {
                    continue;
                }

                $payment->allocations()->create([
                    'payment_schedule_id' => $schedule->id,
                    'invoice_header_id' => $schedule->invoice_header_id,
                    'allocated_amount' => $allocatedAmount,
                    'note' => $note,
                ]);

                $receivedAmount = bcadd($schedule->received_amount, $allocatedAmount, 2);
                $outstandingAmount = bcsub($schedule->scheduled_amount, $receivedAmount, 2);
                $status = bccomp($outstandingAmount, '0.00', 2) === 0 ? 'closed' : 'partial';

                $schedule->forceFill([
                    'received_amount' => $receivedAmount,
                    'outstanding_amount' => $outstandingAmount,
                    'status' => $status,
                    'closed_at' => $status === 'closed' ? now() : null,
                ])->save();

                $remaining = bcsub($remaining, $allocatedAmount, 2);
                $totalAllocated = bcadd($totalAllocated, $allocatedAmount, 2);
            }

            $payment->forceFill([
                'status' => bccomp($remaining, '0.00', 2) === 1 ? 'review_required' : 'allocated',
                'unapplied_amount' => $remaining,
            ])->save();

            $this->auditLogService->record(new AuditLogData(
                event: 'payment.registered_oldest_first',
                auditable: $payment,
                afterValues: [
                    'customer_id' => $customerId,
                    'payment_date' => $payment->payment_date?->toDateString(),
                    'payment_method' => $payment->payment_method,
                    'amount' => $payment->amount,
                    'allocated_amount' => $totalAllocated,
                    'unapplied_amount' => $payment->unapplied_amount,
                ],
                reason: $reason,
            ));

            return $payment->refresh()->load(['customer', 'allocations']);
        });
    }
}
