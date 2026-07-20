<?php

namespace App\Services\Billing;

use App\Exceptions\Billing\InvoiceCancellationException;
use App\Exceptions\StateMachine\InvalidStatusTransitionException;
use App\Models\InvoiceHeader;
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
}
