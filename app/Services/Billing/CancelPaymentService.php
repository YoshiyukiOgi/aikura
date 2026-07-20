<?php

namespace App\Services\Billing;

use App\Exceptions\Billing\PaymentCancellationException;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

class CancelPaymentService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly EnsureReceivableMonthlyBalancePeriodIsOpenService $ensureReceivableMonthlyBalancePeriodIsOpenService,
    ) {}

    public function cancel(Payment $payment, string $reason): Payment
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw PaymentCancellationException::emptyReason();
        }

        return DB::transaction(function () use ($payment, $reason): Payment {
            $payment = Payment::query()
                ->with('allocations')
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if ($payment->status === 'cancelled') {
                throw PaymentCancellationException::alreadyCancelled($payment->id);
            }
            if ($payment->is_legacy_history) {
                throw new \DomainException('Access移行入金は履歴のため取り消せません。');
            }

            $this->ensureReceivableMonthlyBalancePeriodIsOpenService
                ->ensureOpen($payment->payment_date->toDateString());

            foreach ($payment->allocations as $allocation) {
                $schedule = PaymentSchedule::query()
                    ->lockForUpdate()
                    ->findOrFail($allocation->payment_schedule_id);

                $receivedAmount = bcsub($schedule->received_amount, $allocation->allocated_amount, 2);
                $outstandingAmount = bcadd($schedule->outstanding_amount, $allocation->allocated_amount, 2);

                $schedule->forceFill([
                    'received_amount' => $receivedAmount,
                    'outstanding_amount' => $outstandingAmount,
                    'status' => bccomp($receivedAmount, '0.00', 2) === 0 ? 'open' : 'partial',
                    'closed_at' => null,
                ])->save();
            }

            $beforeValues = [
                'status' => $payment->status,
                'cancelled_at' => $payment->cancelled_at?->toISOString(),
                'cancelled_reason' => $payment->cancelled_reason,
            ];

            $payment->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_reason' => $reason,
            ])->save();

            $this->auditLogService->record(new AuditLogData(
                event: 'payment.cancelled',
                auditable: $payment->refresh(),
                beforeValues: $beforeValues,
                afterValues: [
                    'status' => 'cancelled',
                    'cancelled_at' => $payment->cancelled_at?->toISOString(),
                    'cancelled_reason' => $payment->cancelled_reason,
                ],
                reason: $reason,
            ));

            return $payment->refresh()->load(['customer', 'allocations']);
        });
    }
}
