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
        private readonly CreatePaymentScheduleService $createPaymentScheduleService,
    ) {}

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

            if ($invoice->lines->isEmpty() && ! $this->hasReceivableActivity($invoice)) {
                throw InvoiceConfirmationException::noLines($invoice->id);
            }

            $this->ensureConsumptionTaxFilingPeriodIsOpenService
                ->ensureOpen($invoice->invoice_date->toDateString());
            $this->ensureReceivableMonthlyBalancePeriodIsOpenService
                ->ensureOpen($invoice->invoice_date->toDateString());

            $subtotal = '0.00';
            $tax = '0.00';

            foreach ($invoice->lines as $line) {
                $subtotal = bcadd($subtotal, $line->amount, 2);
                $tax = bcadd($tax, $line->tax_amount, 2);
            }

            $currentInvoiceAmount = bcadd($subtotal, $tax, 2);
            $total = $this->invoiceTotalAmount(
                (string) $invoice->previous_balance_amount,
                (string) $invoice->period_payment_amount,
                $currentInvoiceAmount,
            );

            $invoice->update([
                'current_sales_amount' => $subtotal,
                'current_tax_amount' => $tax,
                'current_invoice_amount' => $currentInvoiceAmount,
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

            $invoice = $invoice->refresh()->load(['customer', 'billingCycle', 'lines']);

            $this->createPaymentScheduleService->create(
                $invoice,
                $reason ?? '請求書発行時に入金予定を自動作成'
            );

            return $invoice->refresh()->load(['customer', 'billingCycle', 'lines', 'paymentSchedule']);
        });
    }

    private function invoiceTotalAmount(string $previousBalance, string $periodPayment, string $currentInvoiceAmount): string
    {
        $amount = bcsub(bcadd($previousBalance, $currentInvoiceAmount, 2), $periodPayment, 2);

        return bccomp($amount, '0.00', 2) > 0 ? $amount : '0.00';
    }

    private function hasReceivableActivity(InvoiceHeader $invoice): bool
    {
        return bccomp((string) $invoice->previous_balance_amount, '0.00', 2) !== 0
            || bccomp((string) $invoice->period_payment_amount, '0.00', 2) !== 0;
    }
}
