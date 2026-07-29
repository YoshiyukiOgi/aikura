<?php

namespace App\Services\Billing;

use App\Exceptions\Billing\InvoiceCancellationException;
use App\Exceptions\StateMachine\InvalidStatusTransitionException;
use App\Models\InvoiceHeader;
use App\Models\PaymentSchedule;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use App\Services\StateMachine\StatusTransitionService;
use App\Services\Tax\EnsureConsumptionTaxFilingPeriodIsOpenService;
use Illuminate\Support\Facades\DB;

class CancelInvoiceService
{
    public function __construct(
        private readonly StatusTransitionService $statusTransitionService,
        private readonly AuditLogService $auditLogService,
        private readonly EnsureConsumptionTaxFilingPeriodIsOpenService $ensureConsumptionTaxFilingPeriodIsOpenService,
        private readonly EnsureReceivableMonthlyBalancePeriodIsOpenService $ensureReceivableMonthlyBalancePeriodIsOpenService,
    ) {
    }

    public function cancel(InvoiceHeader $invoice, string $reason): InvoiceHeader
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw InvoiceCancellationException::emptyReason();
        }

        return DB::transaction(function () use ($invoice, $reason): InvoiceHeader {
            $invoice = InvoiceHeader::query()
                ->with('lines')
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            $beforeStatus = $invoice->status;

            if ($invoice->status !== 'draft') {
                $this->ensureConsumptionTaxFilingPeriodIsOpenService
                    ->ensureOpen($invoice->invoice_date->toDateString());
                $this->ensureReceivableMonthlyBalancePeriodIsOpenService
                    ->ensureOpen($invoice->invoice_date->toDateString());
            }

            try {
                $this->statusTransitionService->transition(
                    model: $invoice,
                    machine: 'invoice',
                    to: 'cancelled',
                    reason: $reason,
                    audit: false,
                );
            } catch (InvalidStatusTransitionException $exception) {
                throw InvoiceCancellationException::notCancellable($invoice->id, $beforeStatus);
            }

            $invoice->forceFill([
                'cancelled_at' => now(),
                'cancelled_reason' => $reason,
            ])->save();

            $this->closePaymentSchedule($invoice, $reason);

            $this->auditLogService->record(new AuditLogData(
                event: 'invoice.cancelled',
                auditable: $invoice->refresh(),
                beforeValues: ['status' => $beforeStatus],
                afterValues: [
                    'status' => 'cancelled',
                    'cancelled_at' => $invoice->cancelled_at?->toISOString(),
                    'cancelled_reason' => $invoice->cancelled_reason,
                ],
                reason: $reason,
            ));

            return $invoice->refresh()->load(['customer', 'billingCycle', 'lines']);
        });
    }

    private function closePaymentSchedule(InvoiceHeader $invoice, string $reason): void
    {
        $schedule = PaymentSchedule::query()
            ->where('invoice_header_id', $invoice->id)
            ->whereIn('status', ['open', 'partial'])
            ->lockForUpdate()
            ->first();

        if ($schedule === null) {
            return;
        }

        $before = [
            'status' => $schedule->status,
            'outstanding_amount' => $schedule->outstanding_amount,
            'note' => $schedule->note,
        ];

        $schedule->forceFill([
            'status' => 'closed',
            'outstanding_amount' => '0.00',
            'closed_at' => now(),
            'note' => trim((string) $schedule->note."\n請求取消により回収対象外: {$reason}"),
        ])->save();

        $this->auditLogService->record(new AuditLogData(
            event: 'payment_schedule.closed_by_invoice_cancellation',
            auditable: $schedule,
            beforeValues: $before,
            afterValues: [
                'status' => $schedule->status,
                'outstanding_amount' => $schedule->outstanding_amount,
                'closed_at' => $schedule->closed_at?->toISOString(),
                'note' => $schedule->note,
            ],
            reason: $reason,
        ));
    }
}
