<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\CancelBillingRequest;
use App\Http\Requests\Api\V1\RegisterPaymentRequest;
use App\Http\Requests\Api\V1\ShipmentActionReasonRequest;
use App\Http\Requests\Api\V1\StoreInvoiceRequest;
use App\Http\Requests\Api\V1\StorePaymentScheduleRequest;
use App\Models\InvoiceHeader;
use App\Models\InvoiceLine;
use App\Models\OpeningReceivableBalance;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentSchedule;
use App\Models\SalesReturnLine;
use App\Models\ShipmentHeader;
use App\Services\Billing\BillableShipmentQuery;
use App\Services\Billing\CancelInvoiceService;
use App\Services\Billing\CancelPaymentService;
use App\Services\Billing\ConfirmInvoiceService;
use App\Services\Billing\CreateClosingInvoiceService;
use App\Services\Billing\CreateInvoiceDraftData;
use App\Services\Billing\CreateInvoiceDraftService;
use App\Services\Billing\CreatePaymentScheduleService;
use App\Services\Billing\CustomerMonthlyStatementRow;
use App\Services\Billing\CustomerMonthlyStatementService;
use App\Services\Billing\MonthlyBillingTargetService;
use App\Services\Billing\ReceivableBalance;
use App\Services\Billing\ReceivableBalanceService;
use App\Services\Billing\RegisterPaymentService;
use App\Services\Operations\OperationalPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends ApiController
{
    public function billableShipments(Request $request, BillableShipmentQuery $query): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'billing_target_from' => ['nullable', 'date'],
            'billing_target_to' => ['nullable', 'date', 'after_or_equal:billing_target_from'],
        ]);

        return $this->ok(['shipments' => $query->query(
            customerId: isset($validated['customer_id']) ? (int) $validated['customer_id'] : null,
            billingTargetFrom: $validated['billing_target_from'] ?? null,
            billingTargetTo: $validated['billing_target_to'] ?? null,
        )->get()->map(function (ShipmentHeader $shipment): array {
            $previousBalance = $this->previousOutstandingAmount((int) $shipment->customer_id);

            return [
                'id' => $shipment->id,
                'document_number' => $shipment->document_number,
                'customer_id' => $shipment->customer_id,
                'customer_name' => $shipment->customer?->name,
                'billing_cycle_name' => $shipment->customer?->billingCycle?->name,
                'billing_method' => $shipment->customer?->billingCycle?->billing_method,
                'closing_day' => $shipment->customer?->billingCycle?->closing_day,
                'previous_balance_amount' => $previousBalance,
                'billing_target_date' => $shipment->billing_target_date?->toDateString(),
            ];
        })->values()->all()]);
    }

    public function invoices(Request $request, OperationalPeriod $operationalPeriod): JsonResponse
    {
        $validated = $request->validate([
            'customer' => ['nullable', 'string', 'max:120'],
            'invoice_number' => ['nullable', 'string', 'max:80'],
            'product' => ['nullable', 'string', 'max:120'],
            'invoice_date_from' => ['nullable', 'date'],
            'invoice_date_to' => ['nullable', 'date', 'after_or_equal:invoice_date_from'],
            'closing_day' => ['nullable', 'string', 'max:10'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:40'],
            'document_type' => ['nullable', 'string', 'max:40'],
            'returnable_only' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = InvoiceHeader::query()
            ->with(['customer', 'billingCycle', 'lines.shipmentHeader', 'paymentSchedule'])
            ->whereDate('invoice_date', '>=', $operationalPeriod->startDate())
            ->when($validated['customer'] ?? null, function ($query, string $customer): void {
                $query->whereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', "%{$customer}%"));
            })
            ->when($validated['invoice_number'] ?? null, fn ($query, string $invoiceNumber) => $query->where('invoice_number', 'like', "%{$invoiceNumber}%"))
            ->when($validated['product'] ?? null, function ($query, string $product): void {
                $query->whereHas('lines', function ($lineQuery) use ($product): void {
                    $lineQuery->where(function ($where) use ($product): void {
                        $where->where('product_code', 'like', "%{$product}%")
                            ->orWhere('product_name', 'like', "%{$product}%")
                            ->orWhere('display_name', 'like', "%{$product}%");
                    });
                });
            })
            ->when($validated['invoice_date_from'] ?? null, fn ($query, string $date) => $query->whereDate('invoice_date', '>=', $date))
            ->when($validated['invoice_date_to'] ?? null, fn ($query, string $date) => $query->whereDate('invoice_date', '<=', $date))
            ->when($validated['closing_day'] ?? null, function ($query, string $closingDay): void {
                $query->whereHas('billingCycle', function ($cycleQuery) use ($closingDay): void {
                    $closingDay === 'end'
                        ? $cycleQuery->where('closing_day', '>=', 31)
                        : $cycleQuery->where('closing_day', (int) $closingDay);
                });
            })
            ->when($validated['due_date'] ?? null, fn ($query, string $date) => $query->whereDate('due_date', $date))
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($validated['document_type'] ?? null, fn ($query, string $documentType) => $query->where('document_type', $documentType));

        $perPage = (int) ($validated['per_page'] ?? $validated['limit'] ?? 50);
        $page = (int) ($validated['page'] ?? 1);
        $total = (clone $query)->count();

        $invoices = $query
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (InvoiceHeader $invoice): array => $this->serializeInvoice($invoice))
            ->when((bool) ($validated['returnable_only'] ?? false), fn ($collection) => $collection
                ->map(function (array $invoice): array {
                    $invoice['lines'] = collect($invoice['lines'])
                        ->filter(fn (array $line): bool => (float) ($line['remaining_returnable_quantity'] ?? 0) > 0)
                        ->values()
                        ->all();

                    return $invoice;
                })
                ->filter(fn (array $invoice): bool => $invoice['status'] === 'confirmed'
                    && $invoice['document_type'] !== 'credit_memo'
                    && count($invoice['lines']) > 0))
            ->values()
            ->all();

        return $this->ok([
            'invoices' => $invoices,
            'pagination' => $this->pagination($page, $perPage, $total),
        ]);
    }

    public function monthlyBillingTargets(Request $request, MonthlyBillingTargetService $service): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        return $this->ok([
            'targets' => $service->forMonth((int) $validated['year'], (int) $validated['month'])->all(),
        ]);
    }

    public function invoice(InvoiceHeader $invoice, OperationalPeriod $operationalPeriod): JsonResponse
    {
        abort_if($operationalPeriod->isLocked($invoice->invoice_date?->toDateString()), 404);

        return $this->ok([
            'invoice' => $this->serializeInvoice($invoice->load(['customer', 'billingCycle', 'lines.shipmentHeader', 'paymentSchedule'])),
        ]);
    }

    public function createInvoice(StoreInvoiceRequest $request, CreateInvoiceDraftService $service, OperationalPeriod $operationalPeriod): JsonResponse
    {
        $validated = $request->validated();
        $operationalPeriod->ensureOpen($validated['invoice_date'], '請求日');
        if (isset($validated['billing_period_start'])) {
            $operationalPeriod->ensureOpen($validated['billing_period_start'], '請求期間開始日');
        }

        $invoice = $service->create(new CreateInvoiceDraftData(
            customerId: (int) $validated['customer_id'],
            invoiceDate: $validated['invoice_date'],
            billingPeriodStart: $validated['billing_period_start'] ?? null,
            billingPeriodEnd: $validated['billing_period_end'] ?? null,
            dueDate: $validated['due_date'] ?? null,
            note: $validated['note'] ?? null,
            reason: $validated['reason'] ?? null,
            shipmentHeaderIds: isset($validated['shipment_header_ids'])
                ? array_map('intval', $validated['shipment_header_ids'])
                : null,
            includeCarriedForward: (bool) ($validated['include_carried_forward'] ?? true),
        ));

        return $this->created(['invoice' => $this->serializeInvoice($invoice)]);
    }

    public function createClosingInvoice(Request $request, CreateClosingInvoiceService $service, OperationalPeriod $operationalPeriod): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'closing_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $operationalPeriod->ensureOpen($validated['closing_date'], '締日');

        $invoice = $service->create(
            customerId: (int) $validated['customer_id'],
            closingDate: $validated['closing_date'],
            dueDate: $validated['due_date'] ?? null,
            note: $validated['note'] ?? null,
            reason: $validated['reason'] ?? null,
        );

        return $this->created(['invoice' => $this->serializeInvoice($invoice)]);
    }

    public function confirmInvoice(
        ShipmentActionReasonRequest $request,
        InvoiceHeader $invoice,
        ConfirmInvoiceService $service,
        OperationalPeriod $operationalPeriod,
    ): JsonResponse {
        $operationalPeriod->ensureOpen($invoice->invoice_date?->toDateString(), '請求日');
        $confirmed = $service->confirm($invoice, $request->validated('reason'));

        return $this->ok(['invoice' => $this->serializeInvoice($confirmed)]);
    }

    public function cancelInvoice(
        CancelBillingRequest $request,
        InvoiceHeader $invoice,
        CancelInvoiceService $service,
        OperationalPeriod $operationalPeriod,
    ): JsonResponse {
        $operationalPeriod->ensureOpen($invoice->invoice_date?->toDateString(), '請求日');
        $cancelled = $service->cancel($invoice, $request->validated('reason'));

        return $this->ok(['invoice' => $this->serializeInvoice($cancelled)]);
    }

    public function paymentSchedules(Request $request, OperationalPeriod $operationalPeriod): JsonResponse
    {
        $validated = $request->validate([
            'customer' => ['nullable', 'string', 'max:120'],
            'invoice_number' => ['nullable', 'string', 'max:80'],
            'expected_payment_from' => ['nullable', 'date'],
            'expected_payment_to' => ['nullable', 'date', 'after_or_equal:expected_payment_from'],
            'invoice_date_from' => ['nullable', 'date'],
            'invoice_date_to' => ['nullable', 'date', 'after_or_equal:invoice_date_from'],
            'closing_day' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', 'string', 'max:40'],
            'only_outstanding' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = PaymentSchedule::query()
            ->with(['invoiceHeader', 'customer'])
            ->whereHas('invoiceHeader', fn ($invoiceQuery) => $invoiceQuery
                ->whereDate('invoice_date', '>=', $operationalPeriod->startDate())
                ->whereNotIn('status', ['draft', 'cancelled'])
                ->whereNull('cancelled_at'))
            ->when($validated['customer'] ?? null, function ($query, string $customer): void {
                $query->whereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', "%{$customer}%"));
            })
            ->when($validated['invoice_number'] ?? null, function ($query, string $invoiceNumber): void {
                $query->whereHas('invoiceHeader', fn ($invoiceQuery) => $invoiceQuery->where('invoice_number', 'like', "%{$invoiceNumber}%"));
            })
            ->when($validated['expected_payment_from'] ?? null, fn ($query, string $date) => $query->whereDate('expected_payment_date', '>=', $date))
            ->when($validated['expected_payment_to'] ?? null, fn ($query, string $date) => $query->whereDate('expected_payment_date', '<=', $date))
            ->when($validated['invoice_date_from'] ?? null, function ($query, string $date): void {
                $query->whereHas('invoiceHeader', fn ($invoiceQuery) => $invoiceQuery->whereDate('invoice_date', '>=', $date));
            })
            ->when($validated['invoice_date_to'] ?? null, function ($query, string $date): void {
                $query->whereHas('invoiceHeader', fn ($invoiceQuery) => $invoiceQuery->whereDate('invoice_date', '<=', $date));
            })
            ->when($validated['closing_day'] ?? null, function ($query, string $closingDay): void {
                $query->whereHas('invoiceHeader.billingCycle', function ($cycleQuery) use ($closingDay): void {
                    $closingDay === 'end'
                        ? $cycleQuery->where('closing_day', '>=', 31)
                        : $cycleQuery->where('closing_day', (int) $closingDay);
                });
            })
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when((bool) ($validated['only_outstanding'] ?? false), fn ($query) => $query->where('outstanding_amount', '>', 0));

        $perPage = (int) ($validated['per_page'] ?? $validated['limit'] ?? 50);
        $page = (int) ($validated['page'] ?? 1);
        $total = (clone $query)->count();

        $schedules = $query
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (PaymentSchedule $schedule): array => $this->serializePaymentSchedule($schedule))
            ->values()
            ->all();

        return $this->ok([
            'payment_schedules' => $schedules,
            'pagination' => $this->pagination($page, $perPage, $total),
        ]);
    }

    public function createPaymentSchedule(
        StorePaymentScheduleRequest $request,
        CreatePaymentScheduleService $service,
    ): JsonResponse {
        $validated = $request->validated();

        $invoice = InvoiceHeader::findOrFail((int) $validated['invoice_header_id']);
        app(OperationalPeriod::class)->ensureOpen($invoice->invoice_date?->toDateString(), '請求日');

        $schedule = $service->create(
            $invoice,
            $validated['reason'] ?? null,
        );

        return $this->created(['payment_schedule' => $this->serializePaymentSchedule($schedule)]);
    }

    public function payments(Request $request, OperationalPeriod $operationalPeriod): JsonResponse
    {
        $validated = $request->validate([
            'customer' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:40'],
            'payment_date_from' => ['nullable', 'date'],
            'payment_date_to' => ['nullable', 'date', 'after_or_equal:payment_date_from'],
            'has_unapplied' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Payment::query()
            ->with(['customer', 'allocations.invoiceHeader']);
        $operationalPeriod->applyVisiblePeriodOrImportedHistory(
            $query,
            'payment_date',
            fn ($imported) => $imported->where('is_legacy_history', true),
        );
        $query
            ->when($validated['customer'] ?? null, function ($query, string $customer): void {
                $query->whereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', "%{$customer}%"));
            })
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($validated['payment_date_from'] ?? null, fn ($query, string $date) => $query->whereDate('payment_date', '>=', $date))
            ->when($validated['payment_date_to'] ?? null, fn ($query, string $date) => $query->whereDate('payment_date', '<=', $date))
            ->when((bool) ($validated['has_unapplied'] ?? false), fn ($query) => $query->where('unapplied_amount', '>', 0));

        $perPage = (int) ($validated['per_page'] ?? $validated['limit'] ?? 50);
        $page = (int) ($validated['page'] ?? 1);
        $total = (clone $query)->count();

        $payments = $query
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (Payment $payment): array => $this->serializePayment($payment))
            ->values()
            ->all();

        return $this->ok([
            'payments' => $payments,
            'pagination' => $this->pagination($page, $perPage, $total),
        ]);
    }

    public function registerPayment(RegisterPaymentRequest $request, RegisterPaymentService $service): JsonResponse
    {
        $validated = $request->validated();
        app(OperationalPeriod::class)->ensureOpen($validated['payment_date'], '入金日');

        $payment = isset($validated['customer_id'])
            ? $service->registerForCustomer(
                customerId: (int) $validated['customer_id'],
                amount: $validated['amount'],
                paymentDate: $validated['payment_date'],
                paymentMethod: $validated['payment_method'] ?? 'bank_transfer',
                referenceNumber: $validated['reference_number'] ?? null,
                note: $validated['note'] ?? null,
                reason: $validated['reason'] ?? null,
            )
            : $service->register(
                schedule: PaymentSchedule::findOrFail((int) $validated['payment_schedule_id']),
                amount: $validated['amount'],
                paymentDate: $validated['payment_date'],
                paymentMethod: $validated['payment_method'] ?? 'bank_transfer',
                referenceNumber: $validated['reference_number'] ?? null,
                note: $validated['note'] ?? null,
                reason: $validated['reason'] ?? null,
            );

        return $this->created(['payment' => $this->serializePayment($payment)]);
    }

    public function cancelPayment(CancelBillingRequest $request, Payment $payment, CancelPaymentService $service): JsonResponse
    {
        app(OperationalPeriod::class)->ensureOpen($payment->payment_date?->toDateString(), '入金日');
        $cancelled = $service->cancel($payment, $request->validated('reason'));

        return $this->ok(['payment' => $this->serializePayment($cancelled)]);
    }

    public function receivables(Request $request, ReceivableBalanceService $service): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
        ]);

        if (isset($validated['customer_id'])) {
            return $this->ok([
                'receivable_balance' => $this->serializeReceivableBalance($service->forCustomer((int) $validated['customer_id'])),
            ]);
        }

        return $this->ok([
            'receivable_balances' => $service->allCustomers()
                ->map(fn (ReceivableBalance $balance): array => $this->serializeReceivableBalance($balance))
                ->values()
                ->all(),
        ]);
    }

    public function customerMonthlyStatements(Request $request, CustomerMonthlyStatementService $service): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'include_zero_rows' => ['nullable', 'boolean'],
        ]);

        $isLegacyPeriod = (int) $validated['year'] < 2026 || ((int) $validated['year'] === 2026 && (int) $validated['month'] <= 6);
        if ($isLegacyPeriod) {
            return $this->ok([
                'year' => (int) $validated['year'],
                'month' => (int) $validated['month'],
                'warning' => '2026年6月以前は移行前データのため、この帳票では正しい売掛残高を表示できません。2026年7月以降を指定してください。',
                'rows' => [],
                'totals' => [],
                'totals_by_settlement_receivable_category' => [],
            ]);
        }

        $rows = $service->forMonth((int) $validated['year'], (int) $validated['month'], (bool) ($validated['include_zero_rows'] ?? false));

        return $this->ok([
            'year' => (int) $validated['year'],
            'month' => (int) $validated['month'],
            'rows' => $rows->map(fn (CustomerMonthlyStatementRow $row): array => $row->toArray())->values()->all(),
            'totals' => $service->totals($rows),
            'totals_by_settlement_receivable_category' => $service->totalsBySettlementReceivableCategory($rows),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInvoice(InvoiceHeader $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'document_type' => $invoice->document_type,
            'customer_id' => $invoice->customer_id,
            'customer_name' => $invoice->customer?->name,
            'billing_cycle_name' => $invoice->billingCycle?->name,
            'billing_method' => $invoice->billingCycle?->billing_method,
            'source_sales_return_header_id' => $invoice->source_sales_return_header_id,
            'invoice_date' => $invoice->invoice_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'billing_period_start' => $invoice->billing_period_start?->toDateString(),
            'billing_period_end' => $invoice->billing_period_end?->toDateString(),
            'previous_balance_amount' => $invoice->previous_balance_amount,
            'period_payment_amount' => $invoice->period_payment_amount,
            'carried_forward_amount' => $invoice->carried_forward_amount,
            'current_sales_amount' => $invoice->current_sales_amount,
            'current_tax_amount' => $invoice->current_tax_amount,
            'current_invoice_amount' => $invoice->current_invoice_amount,
            'payment_request_amount' => $this->paymentRequestAmount($invoice),
            'tax_calculation_unit' => $invoice->tax_calculation_unit,
            'subtotal_amount' => $invoice->subtotal_amount,
            'tax_amount' => $invoice->tax_amount,
            'total_amount' => $invoice->total_amount,
            'confirmed_at' => $invoice->confirmed_at?->toISOString(),
            'payment_schedule_id' => $invoice->paymentSchedule?->id,
            'payment_schedule_status' => $invoice->paymentSchedule?->status,
            'cancelled_reason' => $invoice->cancelled_reason,
            'cancelled_at' => $invoice->cancelled_at?->toISOString(),
            'lines' => $invoice->lines
                ->map(function (InvoiceLine $line): array {
                    $remaining = $this->remainingReturnableQuantity($line);

                    return [
                        'id' => $line->id,
                        'line_no' => $line->line_no,
                        'shipment_header_id' => $line->shipment_header_id,
                        'shipment_document_number' => $line->shipmentHeader?->document_number,
                        'shipment_document_date' => $line->shipmentHeader?->document_date?->toDateString(),
                        'shipment_line_id' => $line->shipment_line_id,
                        'source_invoice_line_id' => $line->source_invoice_line_id,
                        'source_sales_return_line_id' => $line->source_sales_return_line_id,
                        'product_id' => $line->product_id,
                        'product_code' => $line->product_code,
                        'product_name' => $line->product_name,
                        'quantity' => $line->quantity,
                        'quantity_display' => $this->formatQuantity($line->quantity),
                        'remaining_returnable_quantity' => $remaining,
                        'remaining_returnable_quantity_display' => $this->formatQuantity($remaining),
                        'unit_price' => $line->unit_price,
                        'amount' => $line->amount,
                        'tax_rate' => $line->tax_rate,
                        'tax_amount' => $line->tax_amount,
                        'total_amount' => $line->total_amount,
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    private function formatQuantity(mixed $quantity): string
    {
        $formatted = rtrim(rtrim(number_format((float) $quantity, 4, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    private function remainingReturnableQuantity(InvoiceLine $line): string
    {
        if ($line->quantity <= 0) {
            return '0.0000';
        }

        $returned = SalesReturnLine::query()
            ->where('source_invoice_line_id', $line->id)
            ->whereHas('salesReturnHeader', fn ($query) => $query->whereNull('cancelled_at'))
            ->sum('quantity');

        $remaining = bcsub((string) $line->quantity, bcadd((string) $returned, '0', 4), 4);

        return bccomp($remaining, '0', 4) > 0 ? $remaining : '0.0000';
    }

    private function paymentRequestAmount(InvoiceHeader $invoice): string
    {
        if (bccomp((string) $invoice->carried_forward_amount, '0.00', 2) > 0) {
            return bcadd((string) $invoice->total_amount, '0', 2);
        }

        return bcadd((string) $invoice->previous_balance_amount, (string) $invoice->total_amount, 2);
    }

    private function previousOutstandingAmount(int $customerId): string
    {
        $amount = PaymentSchedule::query()
            ->where('customer_id', $customerId)
            ->whereHas('invoiceHeader', function ($query): void {
                $query
                    ->whereNotIn('status', ['draft', 'cancelled'])
                    ->whereNull('cancelled_at');
            })
            ->whereIn('status', ['open', 'partial'])
            ->where('outstanding_amount', '>', 0)
            ->sum('outstanding_amount');

        $opening = OpeningReceivableBalance::query()
            ->where('customer_id', $customerId)
            ->whereIn('status', ['calculated', 'reconciled'])
            ->orderByDesc('as_of_date')
            ->orderByDesc('id')
            ->value('opening_balance_amount') ?? '0.00';

        return bcadd((string) $amount, (string) $opening, 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePaymentSchedule(PaymentSchedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'invoice_header_id' => $schedule->invoice_header_id,
            'invoice_number' => $schedule->invoiceHeader?->invoice_number,
            'customer_id' => $schedule->customer_id,
            'customer_name' => $schedule->customer?->name,
            'status' => $schedule->status,
            'expected_payment_date' => $schedule->expected_payment_date?->toDateString(),
            'scheduled_amount' => $schedule->scheduled_amount,
            'received_amount' => $schedule->received_amount,
            'outstanding_amount' => $schedule->outstanding_amount,
            'closed_at' => $schedule->closed_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePayment(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'is_legacy_history' => $payment->is_legacy_history,
            'customer_id' => $payment->customer_id,
            'customer_name' => $payment->customer?->name,
            'status' => $payment->status,
            'payment_date' => $payment->payment_date?->toDateString(),
            'payment_method' => $payment->payment_method,
            'amount' => $payment->amount,
            'unapplied_amount' => $payment->unapplied_amount,
            'reference_number' => $payment->reference_number,
            'note' => $payment->note,
            'cancelled_reason' => $payment->cancelled_reason,
            'cancelled_at' => $payment->cancelled_at?->toISOString(),
            'allocations' => $payment->allocations
                ->map(fn (PaymentAllocation $allocation): array => [
                    'id' => $allocation->id,
                    'payment_schedule_id' => $allocation->payment_schedule_id,
                    'invoice_header_id' => $allocation->invoice_header_id,
                    'invoice_number' => $allocation->invoiceHeader?->invoice_number,
                    'allocated_amount' => $allocation->allocated_amount,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReceivableBalance(ReceivableBalance $balance): array
    {
        return [
            'customer_id' => $balance->customerId,
            'customer_code' => $balance->customerCode,
            'customer_name' => $balance->customerName,
            'scheduled_amount' => $balance->scheduledAmount,
            'received_amount' => $balance->receivedAmount,
            'outstanding_amount' => $balance->outstandingAmount,
            'open_schedule_count' => $balance->openScheduleCount,
            'partial_schedule_count' => $balance->partialScheduleCount,
            'closed_schedule_count' => $balance->closedScheduleCount,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function pagination(int $page, int $perPage, int $total): array
    {
        return [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max(1, (int) ceil($total / max(1, $perPage))),
        ];
    }
}
