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
        private readonly EnsureInternalMonthlyBalancePeriodIsOpenService $ensureInternalMonthlyBalancePeriodIsOpenService,
    ) {}

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
                if ($invoice->document_type === 'internal_statement') {
                    $this->ensureInternalMonthlyBalancePeriodIsOpenService->ensureOpen($invoice->invoice_date->toDateString());
                } else {
                    $this->ensureReceivableMonthlyBalancePeriodIsOpenService->ensureOpen($invoice->invoice_date->toDateString());
                }
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

            if ($invoice->document_type !== 'internal_statement') {
                $this->restoreCarriedForwardSchedules($invoice, $reason);
                $this->closePaymentSchedule($invoice, $reason);
            }

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

    private function restoreCarriedForwardSchedules(InvoiceHeader $invoice, string $reason): void
    {
        PaymentSchedule::query()
            ->where('carried_forward_to_invoice_header_id', $invoice->id)
            ->lockForUpdate()
            ->get()
            ->each(function (PaymentSchedule $schedule) use ($invoice, $reason): void {
                $outstandingAmount = bcsub((string) $schedule->scheduled_amount, (string) $schedule->received_amount, 2);
                $status = bccomp($outstandingAmount, '0.00', 2) > 0
                    ? (bccomp((string) $schedule->received_amount, '0.00', 2) > 0 ? 'partial' : 'open')
                    : 'closed';

                $before = [
                    'status' => $schedule->status,
                    'outstanding_amount' => $schedule->outstanding_amount,
                    'carried_forward_to_invoice_header_id' => $schedule->carried_forward_to_invoice_header_id,
                ];

                $schedule->forceFill([
                    'status' => $status,
                    'outstanding_amount' => $outstandingAmount,
                    'closed_at' => $status === 'closed' ? $schedule->closed_at : null,
                    'carried_forward_to_invoice_header_id' => null,
                    'note' => trim((string) $schedule->note."\n請求取消により繰越を戻す: {$invoice->invoice_number} / {$reason}"),
                ])->save();

                $this->auditLogService->record(new AuditLogData(
                    event: 'payment_schedule.carried_forward_restored',
                    auditable: $schedule,
                    beforeValues: $before,
                    afterValues: [
                        'status' => $schedule->status,
                        'outstanding_amount' => $schedule->outstanding_amount,
                        'carried_forward_to_invoice_header_id' => null,
                    ],
                    reason: $reason,
                ));
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
