<?php

namespace App\Services\Billing;

use App\Exceptions\Billing\InvoiceConfirmationException;
use App\Models\InvoiceHeader;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use App\Services\StateMachine\StatusTransitionService;
use App\Services\Tax\EnsureConsumptionTaxFilingPeriodIsOpenService;
use Illuminate\Support\Facades\DB;

class ConfirmInvoiceService
{
    public function __construct(
        private readonly StatusTransitionService $statusTransitionService,
        private readonly AuditLogService $auditLogService,
        private readonly EnsureConsumptionTaxFilingPeriodIsOpenService $ensureConsumptionTaxFilingPeriodIsOpenService,
        private readonly EnsureReceivableMonthlyBalancePeriodIsOpenService $ensureReceivableMonthlyBalancePeriodIsOpenService,
    ) {
    }

    public function confirm(InvoiceHeader $invoice, ?string $reason = null): InvoiceHeader
    {
        return DB::transaction(function () use ($invoice, $reason): InvoiceHeader {
            $invoice = InvoiceHeader::query()
                ->with('lines')
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            if ($invoice->status !== 'draft') {
                throw InvoiceConfirmationException::notDraft($invoice->id, $invoice->status);
            }

            if ($invoice->lines->isEmpty()) {
                throw InvoiceConfirmationException::noLines($invoice->id);
            }

            $this->ensureConsumptionTaxFilingPeriodIsOpenService
                ->ensureOpen($invoice->invoice_date->toDateString());
            $this->ensureReceivableMonthlyBalancePeriodIsOpenService
                ->ensureOpen($invoice->invoice_date->toDateString());

            $subtotal = '0.00';
            $tax = '0.00';
            $total = '0.00';

            foreach ($invoice->lines as $line) {
                $subtotal = bcadd($subtotal, $line->amount, 2);
                $tax = bcadd($tax, $line->tax_amount, 2);
                $total = bcadd($total, $line->total_amount, 2);
            }

            $invoice->update([
                'subtotal_amount' => $subtotal,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'confirmed_at' => now(),
            ]);

            $this->statusTransitionService->transition(
                model: $invoice,
                machine: 'invoice',
                to: 'confirmed',
                reason: $reason,
                audit: false,
            );

            $this->auditLogService->record(new AuditLogData(
                event: 'invoice.confirmed',
                auditable: $invoice->refresh(),
                beforeValues: ['status' => 'draft'],
                afterValues: [
                    'status' => 'confirmed',
                    'subtotal_amount' => $invoice->subtotal_amount,
                    'tax_amount' => $invoice->tax_amount,
                    'total_amount' => $invoice->total_amount,
                    'confirmed_at' => $invoice->confirmed_at?->toISOString(),
                ],
                reason: $reason,
            ));

            return $invoice->refresh()->load(['customer', 'billingCycle', 'lines']);
        });
    }
}
