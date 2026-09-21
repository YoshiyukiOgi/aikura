<?php

namespace App\Services\Billing;

use App\Exceptions\Billing\InvoiceDraftException;
use App\Models\Customer;
use App\Models\InvoiceHeader;
use App\Models\OpeningReceivableBalance;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Models\ShipmentHeader;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use App\Services\NumberSequence\NumberSequenceService;
use App\Services\Operations\OperationalPeriod;
use App\Services\Tax\TaxRoundingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreateInvoiceDraftService
{
    public function __construct(
        private readonly BillableShipmentQuery $billableShipmentQuery,
        private readonly NumberSequenceService $numberSequenceService,
        private readonly AuditLogService $auditLogService,
        private readonly TaxRoundingService $taxRoundingService,
        private readonly OperationalPeriod $operationalPeriod,
        private readonly InternalBalanceService $internalBalanceService,
    ) {}

    public function create(CreateInvoiceDraftData $data): InvoiceHeader
    {
        return DB::transaction(function () use ($data): InvoiceHeader {
            $customer = Customer::query()->with('settlementReceivableCategory')->findOrFail($data->customerId);
            $isInternal = $this->internalBalanceService->isInternal($customer);
            $shipments = $this->resolveShipments($data);

            $invoiceNumber = $this->numberSequenceService
                ->next('invoice_document')
                ->formatted;

            $invoice = InvoiceHeader::create([
                'invoice_number' => $invoiceNumber,
                'status' => 'draft',
                'document_type' => $isInternal ? 'internal_statement' : 'invoice',
                'customer_id' => $customer->id,
                'billing_cycle_id' => $customer->billing_cycle_id,
                'invoice_date' => $data->invoiceDate,
                'billing_period_start' => $data->billingPeriodStart,
                'billing_period_end' => $data->billingPeriodEnd,
                'due_date' => $data->dueDate,
                'previous_balance_amount' => '0.00',
                'period_payment_amount' => '0.00',
                'carried_forward_amount' => '0.00',
                'current_sales_amount' => '0.00',
                'current_tax_amount' => '0.00',
                'current_invoice_amount' => '0.00',
                'tax_calculation_unit' => $customer->tax_calculation_unit,
                'tax_rounding_method' => $customer->tax_rounding_method,
                'amount_rounding_method' => $customer->amount_rounding_method,
                'note' => $data->note,
            ]);

            $lineNo = 1;
            $subtotal = '0.00';
            $taxableGroups = [];
            $legacyAccessTaxGroups = [];

            foreach ($shipments as $shipment) {
                if ($shipment->customer_id !== $customer->id) {
                    throw InvoiceDraftException::shipmentCustomerMismatch($shipment->id);
                }
                $legacyAccessTaxAmount = $this->legacyAccessTaxAmount($shipment);

                foreach ($shipment->lines as $shipmentLine) {
                    if ($shipmentLine->confirmed_unit_price === null || $shipmentLine->confirmed_product_code === null) {
                        throw InvoiceDraftException::shipmentLineMissingSnapshot($shipmentLine->id);
                    }

                    $amount = bcmul($shipmentLine->confirmed_quantity, $shipmentLine->confirmed_unit_price, 2);
                    $taxAmount = $customer->tax_calculation_unit === 'line'
                        ? $this->calculateTaxAmount($amount, $shipmentLine->confirmed_consumption_tax_rate, $customer->tax_rounding_method)
                        : '0.00';
                    $lineTotal = bcadd($amount, $taxAmount, 2);
                    $subtotal = bcadd($subtotal, $amount, 2);

                    if ($customer->tax_calculation_unit === 'invoice'
                        && $legacyAccessTaxAmount === null
                        && $shipmentLine->confirmed_consumption_tax_rate !== null) {
                        $groupKey = $this->taxGroupKey(
                            $shipmentLine->confirmed_consumption_tax_rate_id,
                            $shipmentLine->confirmed_consumption_tax_rate,
                        );
                        $taxableGroups[$groupKey] ??= [
                            'amount' => '0.00',
                            'line_ids' => [],
                        ];
                        $taxableGroups[$groupKey]['amount'] = bcadd($taxableGroups[$groupKey]['amount'], $amount, 2);
                    }

                    $line = $invoice->lines()->create([
                        'shipment_header_id' => $shipment->id,
                        'shipment_line_id' => $shipmentLine->id,
                        'line_no' => $lineNo++,
                        'product_id' => $shipmentLine->product_id,
                        'product_code' => $shipmentLine->confirmed_product_code,
                        'product_name' => $shipmentLine->confirmed_product_name,
                        'display_name' => $shipmentLine->confirmed_display_name,
                        'quantity' => $shipmentLine->confirmed_quantity,
                        'unit_code' => $shipmentLine->confirmed_unit_code,
                        'unit_name' => $shipmentLine->confirmed_unit_name,
                        'unit_price' => $shipmentLine->confirmed_unit_price,
                        'amount' => $amount,
                        'consumption_tax_category_id' => $shipmentLine->confirmed_consumption_tax_category_id,
                        'consumption_tax_category_code' => $shipmentLine->confirmed_consumption_tax_category_code,
                        'consumption_tax_category_name' => $shipmentLine->confirmed_consumption_tax_category_name,
                        'consumption_taxability' => $shipmentLine->confirmed_consumption_taxability,
                        'consumption_tax_rate_id' => $shipmentLine->confirmed_consumption_tax_rate_id,
                        'tax_rate' => $shipmentLine->confirmed_consumption_tax_rate,
                        'consumption_tax_rate_effective_from' => $shipmentLine->confirmed_consumption_tax_rate_effective_from,
                        'tax_amount' => $taxAmount,
                        'total_amount' => $lineTotal,
                        'note' => $shipmentLine->note,
                    ]);

                    if ($customer->tax_calculation_unit === 'invoice'
                        && $legacyAccessTaxAmount === null
                        && $shipmentLine->confirmed_consumption_tax_rate !== null) {
                        $taxableGroups[$groupKey]['line_ids'][] = $line->id;
                    }
                    if ($customer->tax_calculation_unit === 'invoice' && $legacyAccessTaxAmount !== null) {
                        $legacyAccessTaxGroups[(string) $shipment->id] ??= [
                            'tax_amount' => $legacyAccessTaxAmount,
                            'line_ids' => [],
                            'weights' => [],
                        ];
                        $legacyAccessTaxGroups[(string) $shipment->id]['line_ids'][] = $line->id;
                        $legacyAccessTaxGroups[(string) $shipment->id]['weights'][$line->id] = $this->legacyAccessTaxWeight($shipmentLine);
                    }
                }
            }

            if ($customer->tax_calculation_unit === 'invoice') {
                $this->applyInvoiceUnitTax($invoice, $taxableGroups, $customer->tax_rounding_method);
                $this->applyLegacyAccessTax($invoice, $legacyAccessTaxGroups);
            }

            $invoice->load('lines');
            $tax = '0.00';
            $total = '0.00';

            foreach ($invoice->lines as $line) {
                $tax = bcadd($tax, $line->tax_amount, 2);
                $total = bcadd($total, $line->total_amount, 2);
            }

            $previousBalance = $isInternal
                ? $this->internalBalanceService->balanceBefore($customer, $data->billingPeriodStart ?? $data->invoiceDate)
                : $this->previousBalanceAmount($customer, $invoice, $data->billingPeriodStart);
            $periodPayment = $isInternal
                ? $this->internalBalanceService->periodSettlementAmount($customer, $data->billingPeriodStart, $data->billingPeriodEnd)
                : $this->periodPaymentAmount($customer, $data->billingPeriodStart, $data->billingPeriodEnd);

            if ($shipments->isEmpty() && ! $this->hasReceivableActivity($previousBalance, $periodPayment, $data->includeCarriedForward)) {
                throw InvoiceDraftException::noBillableShipments();
            }

            $carriedForward = $data->includeCarriedForward
                ? $this->carriedForwardAmount($previousBalance, $periodPayment)
                : '0.00';
            $currentInvoiceAmount = bcadd($subtotal, $tax, 2);
            $totalAmount = $data->includeCarriedForward
                ? $this->invoiceTotalAmount($previousBalance, $periodPayment, $currentInvoiceAmount)
                : $currentInvoiceAmount;

            $invoice->update([
                'previous_balance_amount' => $previousBalance,
                'period_payment_amount' => $periodPayment,
                'carried_forward_amount' => $carriedForward,
                'current_sales_amount' => $subtotal,
                'current_tax_amount' => $tax,
                'current_invoice_amount' => $currentInvoiceAmount,
                'subtotal_amount' => $subtotal,
                'tax_amount' => $tax,
                'total_amount' => $totalAmount,
            ]);

            $this->auditLogService->record(new AuditLogData(
                event: 'invoice.draft_created',
                auditable: $invoice->refresh(),
                afterValues: [
                    'invoice_number' => $invoice->invoice_number,
                    'status' => $invoice->status,
                    'customer_id' => $invoice->customer_id,
                    'shipment_header_ids' => $shipments->pluck('id')->all(),
                    'line_count' => $invoice->lines()->count(),
                    'total_amount' => $invoice->total_amount,
                ],
                reason: $data->reason,
            ));

            return $invoice->refresh()->load(['customer', 'billingCycle', 'lines']);
        });
    }

    private function previousBalanceAmount(Customer $customer, InvoiceHeader $invoice, ?string $billingPeriodStart): string
    {
        $amount = PaymentSchedule::query()
            ->where('customer_id', $customer->id)
            ->whereHas('invoiceHeader', function ($query) use ($invoice): void {
                $query
                    ->whereNotIn('status', ['draft', 'cancelled'])
                    ->whereNull('cancelled_at')
                    ->whereDate('invoice_date', '>=', $this->operationalPeriod->startDate())
                    ->whereDate('invoice_date', '<', $invoice->invoice_date->toDateString());
            })
            ->whereIn('status', ['open', 'partial'])
            ->where('outstanding_amount', '>', 0)
            ->where('invoice_header_id', '!=', $invoice->id)
            ->sum('outstanding_amount');

        return bcadd(
            bcadd((string) $amount, '0', 2),
            $this->openingReceivableAmount($customer, $invoice, $billingPeriodStart),
            2,
        );
    }

    private function openingReceivableAmount(Customer $customer, InvoiceHeader $invoice, ?string $billingPeriodStart): string
    {
        if ($billingPeriodStart === null) {
            return '0.00';
        }

        $opening = OpeningReceivableBalance::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['calculated', 'reconciled'])
            ->where('opening_balance_amount', '!=', 0)
            ->whereDate('as_of_date', '>=', $this->operationalPeriod->startDate())
            ->whereDate('as_of_date', '<=', $billingPeriodStart)
            ->orderByDesc('as_of_date')
            ->orderByDesc('id')
            ->first();

        if ($opening === null) {
            return '0.00';
        }

        $alreadyCarried = InvoiceHeader::query()
            ->where('customer_id', $customer->id)
            ->where('id', '!=', $invoice->id)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereNull('cancelled_at')
            ->whereDate('invoice_date', '>=', $opening->as_of_date->toDateString())
            ->whereDate('invoice_date', '<', $invoice->invoice_date->toDateString())
            ->exists();

        if ($alreadyCarried) {
            return '0.00';
        }

        return bcadd((string) $opening->opening_balance_amount, '0', 2);
    }

    private function carriedForwardAmount(string $previousBalance, string $periodPayment): string
    {
        $amount = bcsub($previousBalance, $periodPayment, 2);

        return bccomp($amount, '0.00', 2) > 0 ? $amount : '0.00';
    }

    private function invoiceTotalAmount(string $previousBalance, string $periodPayment, string $currentInvoiceAmount): string
    {
        $amount = bcsub(bcadd($previousBalance, $currentInvoiceAmount, 2), $periodPayment, 2);

        return bccomp($amount, '0.00', 2) > 0 ? $amount : '0.00';
    }

    private function hasReceivableActivity(string $previousBalance, string $periodPayment, bool $includeCarriedForward): bool
    {
        return $includeCarriedForward
            && (bccomp($previousBalance, '0.00', 2) !== 0 || bccomp($periodPayment, '0.00', 2) !== 0);
    }

    private function periodPaymentAmount(Customer $customer, ?string $periodStart, ?string $periodEnd): string
    {
        if ($periodStart === null || $periodEnd === null) {
            return '0.00';
        }

        $amount = Payment::query()
            ->where('customer_id', $customer->id)
            ->whereNull('cancelled_at')
            ->whereBetween('payment_date', [$periodStart, $periodEnd])
            ->sum('amount');

        return bcadd((string) $amount, '0', 2);
    }

    private function calculateTaxAmount(string $amount, mixed $rate, string $roundingMethod): string
    {
        if ($rate === null) {
            return '0.00';
        }

        return $this->taxRoundingService->round(bcmul($amount, (string) $rate, 6), $roundingMethod);
    }

    private function taxGroupKey(mixed $rateId, mixed $rate): string
    {
        if ($rateId !== null) {
            return 'id:'.(string) $rateId;
        }

        return 'rate:'.bcadd((string) $rate, '0', 4);
    }

    private function legacyAccessTaxAmount(ShipmentHeader $shipment): ?string
    {
        if ($shipment->legacy_access_document_number === null || $shipment->legacy_access_consumption_tax_amount === null) {
            return null;
        }

        return bcadd((string) $shipment->legacy_access_consumption_tax_amount, '0', 2);
    }

    private function legacyAccessTaxWeight($shipmentLine): string
    {
        if ($shipmentLine->legacy_access_consumption_tax_amount === null) {
            return '0.00';
        }

        $weight = bcadd((string) $shipmentLine->legacy_access_consumption_tax_amount, '0', 2);

        return bccomp($weight, '0.00', 2) > 0 ? $weight : '0.00';
    }

    /**
     * @param array<string, array{tax_amount: string, line_ids: array<int, int>, weights: array<int, string>}> $groups
     */
    private function applyLegacyAccessTax(InvoiceHeader $invoice, array $groups): void
    {
        $invoice->load('lines');

        foreach ($groups as $group) {
            $lineIds = $group['line_ids'];
            if ($lineIds === []) {
                continue;
            }
            $lines = $invoice->lines->whereIn('id', $lineIds)->values();
            $weights = collect($group['weights']);
            $totalWeight = $weights->reduce(fn (string $sum, string $weight): string => bcadd($sum, $weight, 2), '0.00');
            if (bccomp($totalWeight, '0.00', 2) === 0) {
                $weights = $lines->mapWithKeys(fn ($line): array => [$line->id => (string) $line->amount]);
                $totalWeight = $weights->reduce(fn (string $sum, string $weight): string => bcadd($sum, $weight, 2), '0.00');
            }

            $remainingTax = $group['tax_amount'];
            $lastLineId = $lines->last()?->id;
            foreach ($lines as $line) {
                $lineTax = $line->id === $lastLineId
                    ? $remainingTax
                    : (bccomp($totalWeight, '0.00', 2) === 0
                        ? '0.00'
                        : bcadd(bcdiv(bcmul($group['tax_amount'], (string) $weights->get($line->id, '0.00'), 4), $totalWeight, 4), '0', 2));
                $line->update([
                    'tax_amount' => $lineTax,
                    'total_amount' => bcadd($line->amount, $lineTax, 2),
                ]);
                $remainingTax = bcsub($remainingTax, $lineTax, 2);
            }
        }
    }

    /**
     * @param  array<string, array{amount: string, line_ids: array<int, int>}>  $taxableGroups
     */
    private function applyInvoiceUnitTax(InvoiceHeader $invoice, array $taxableGroups, string $roundingMethod): void
    {
        $invoice->load('lines');

        foreach ($taxableGroups as $group) {
            $lineIds = $group['line_ids'];

            if ($lineIds === []) {
                continue;
            }

            $firstLine = $invoice->lines->firstWhere('id', $lineIds[0]);
            $groupTax = $this->calculateTaxAmount($group['amount'], $firstLine?->tax_rate, $roundingMethod);
            $remainingTax = $groupTax;
            $lastLineId = end($lineIds);

            foreach ($invoice->lines->whereIn('id', $lineIds) as $line) {
                $lineTax = $line->id === $lastLineId
                    ? $remainingTax
                    : (bccomp($group['amount'], '0.00', 2) === 0
                        ? '0.00'
                        : bcadd(bcdiv(bcmul($groupTax, $line->amount, 4), $group['amount'], 4), '0', 2));

                $line->update([
                    'tax_amount' => $lineTax,
                    'total_amount' => bcadd($line->amount, $lineTax, 2),
                ]);

                $remainingTax = bcsub($remainingTax, $lineTax, 2);
            }
        }
    }

    /**
     * @return Collection<int, ShipmentHeader>
     */
    private function resolveShipments(CreateInvoiceDraftData $data): Collection
    {
        if ($data->shipmentHeaderIds !== null) {
            return ShipmentHeader::query()
                ->with('lines')
                ->whereIn('id', $data->shipmentHeaderIds)
                ->lockForUpdate()
                ->get()
                ->each(function (ShipmentHeader $shipment): void {
                    $isBillable = $this->billableShipmentQuery
                        ->query()
                        ->where('shipment_headers.id', $shipment->id)
                        ->exists();

                    if (! $isBillable) {
                        throw InvoiceDraftException::shipmentNotBillable($shipment->id);
                    }
                });
        }

        return $this->billableShipmentQuery
            ->query(
                customerId: $data->customerId,
                billingTargetFrom: $data->billingPeriodStart,
                billingTargetTo: $data->billingPeriodEnd,
            )
            ->lockForUpdate()
            ->get();
    }
}
