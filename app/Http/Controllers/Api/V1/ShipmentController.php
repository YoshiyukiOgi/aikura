<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\CancelShipmentRequest;
use App\Http\Requests\Api\V1\ShipmentActionReasonRequest;
use App\Http\Requests\Api\V1\StoreShipmentRequest;
use App\Models\ShipmentHeader;
use App\Models\ShipmentInstruction;
use App\Models\ShipmentLine;
use App\Services\Operations\OperationalPeriod;
use App\Services\Shipment\ApplyDraftShipmentPricingService;
use App\Services\Shipment\CancelShipmentService;
use App\Services\Shipment\ConfirmShipmentService;
use App\Services\Shipment\CreateDraftShipmentData;
use App\Services\Shipment\CreateDraftShipmentFromInstructionService;
use App\Services\Shipment\CreateDraftShipmentFromPickData;
use App\Services\Shipment\CreateDraftShipmentFromPickService;
use App\Services\Shipment\CreateDraftShipmentLineData;
use App\Services\Shipment\CreateDraftShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentController extends ApiController
{
    public function index(OperationalPeriod $operationalPeriod): JsonResponse
    {
        $query = ShipmentHeader::query()
            ->with(['customer', 'sourceShipmentPick.shipmentInstruction.lines.salesOrder', 'sourceShipmentInstruction.lines.salesOrder', 'lines.product.capacityUnit', 'lines.unit', 'lines.confirmedCapacityUnit']);
        $shipments = $operationalPeriod->applyVisiblePeriod($query, 'document_date')
            ->withCount('invoiceLines')
            ->where('status', 'draft')
            ->whereDoesntHave('invoiceLines.invoiceHeader', fn ($builder) => $builder
                ->where('document_type', '!=', 'credit_memo')
                ->where('status', '!=', 'cancelled'))
            ->orderByDesc('id')
            ->get()
            ->map(fn (ShipmentHeader $shipment): array => $this->serializeShipment($shipment))
            ->values()
            ->all();

        return $this->ok([
            'shipments' => $shipments,
        ]);
    }

    public function history(Request $request, OperationalPeriod $operationalPeriod): JsonResponse
    {
        $validated = $request->validate([
            'customer' => ['nullable', 'string', 'max:160'],
            'document_number' => ['nullable', 'string', 'max:80'],
            'sales_order_number' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'string', 'in:draft,confirmed,cancelled'],
            'invoice_status' => ['nullable', 'string', 'in:all,invoiced,uninvoiced'],
            'document_date_from' => ['nullable', 'date'],
            'document_date_to' => ['nullable', 'date'],
            'billing_target_from' => ['nullable', 'date'],
            'billing_target_to' => ['nullable', 'date'],
            'sort' => ['nullable', 'string', 'in:document_date,document_number,customer,status,billing_target_date,id'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $query = ShipmentHeader::query()
            ->with([
                'customer',
                'sourceShipmentPick.shipmentInstruction.lines.salesOrder',
                'sourceShipmentInstruction.lines.salesOrder',
                'lines.product.capacityUnit',
                'lines.unit',
                'lines.confirmedCapacityUnit',
                'invoiceLines.invoiceHeader',
            ])
            ->withCount('invoiceLines');
        $operationalPeriod->applyVisiblePeriodOrImportedHistory(
            $query,
            'document_date',
            fn ($imported) => $imported->whereNotNull('legacy_access_document_number'),
        );

        if ($customer = trim((string) ($validated['customer'] ?? ''))) {
            $query->whereHas('customer', fn ($builder) => $builder->where('name', 'like', "%{$customer}%"));
        }

        if ($documentNumber = trim((string) ($validated['document_number'] ?? ''))) {
            $query->where('document_number', 'like', "%{$documentNumber}%");
        }

        if ($status = $validated['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($from = $validated['document_date_from'] ?? null) {
            $query->whereDate('document_date', '>=', $from);
        }

        if ($to = $validated['document_date_to'] ?? null) {
            $query->whereDate('document_date', '<=', $to);
        }

        if ($from = $validated['billing_target_from'] ?? null) {
            $query->whereDate('billing_target_date', '>=', $from);
        }

        if ($to = $validated['billing_target_to'] ?? null) {
            $query->whereDate('billing_target_date', '<=', $to);
        }

        if ($salesOrderNumber = trim((string) ($validated['sales_order_number'] ?? ''))) {
            $query->where(function ($builder) use ($salesOrderNumber): void {
                $builder
                    ->whereHas('sourceShipmentInstruction.lines.salesOrder', fn ($orderQuery) => $orderQuery->where('order_number', 'like', "%{$salesOrderNumber}%"))
                    ->orWhereHas('sourceShipmentPick.shipmentInstruction.lines.salesOrder', fn ($orderQuery) => $orderQuery->where('order_number', 'like', "%{$salesOrderNumber}%"));
            });
        }

        if (($validated['invoice_status'] ?? 'all') === 'invoiced') {
            $query->whereHas('invoiceLines.invoiceHeader', fn ($builder) => $builder
                ->where('document_type', '!=', 'credit_memo')
                ->where('status', '!=', 'cancelled'));
        } elseif (($validated['invoice_status'] ?? 'all') === 'uninvoiced') {
            $query->whereDoesntHave('invoiceLines.invoiceHeader', fn ($builder) => $builder
                ->where('document_type', '!=', 'credit_memo')
                ->where('status', '!=', 'cancelled'));
        }

        $sort = $validated['sort'] ?? 'document_date';
        $direction = $validated['direction'] ?? 'desc';

        if ($sort === 'customer') {
            $query
                ->leftJoin('customers', 'customers.id', '=', 'shipment_headers.customer_id')
                ->orderBy('customers.name', $direction)
                ->select('shipment_headers.*');
        } else {
            $query->orderBy($sort, $direction);
        }

        $query->orderBy('shipment_headers.id', $direction);

        $perPage = (int) ($validated['per_page'] ?? 50);
        $paginator = $query->paginate($perPage);

        return $this->ok([
            'shipments' => collect($paginator->items())
                ->map(fn (ShipmentHeader $shipment): array => $this->serializeShipmentHistory($shipment))
                ->values()
                ->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(ShipmentHeader $shipment, OperationalPeriod $operationalPeriod): JsonResponse
    {
        abort_if($operationalPeriod->isLocked($shipment->document_date?->toDateString()), 404);

        return $this->ok([
            'shipment' => $this->serializeShipment(
                $shipment->load(['customer', 'sourceShipmentPick.shipmentInstruction.lines.salesOrder', 'sourceShipmentInstruction.lines.salesOrder', 'lines.product.capacityUnit', 'lines.unit', 'lines.confirmedCapacityUnit']),
            ),
        ]);
    }

    public function issueFromInstruction(
        ShipmentInstruction $shipmentInstruction,
        CreateDraftShipmentFromInstructionService $service,
        ApplyDraftShipmentPricingService $pricingService,
    ): JsonResponse {
        $shipment = $service->create($shipmentInstruction);
        $shipment = $pricingService->apply($shipment, '出荷伝票作成時の価格適用');

        if ($shipment->document_issued_at === null) {
            $shipment->forceFill(['document_issued_at' => now()])->save();
        }

        return $this->created(['shipment' => $this->serializeShipment($shipment->refresh()->load(['customer', 'sourceShipmentPick.shipmentInstruction.lines.salesOrder', 'sourceShipmentInstruction.lines.salesOrder', 'lines.product.capacityUnit', 'lines.unit', 'lines.confirmedCapacityUnit']))]);
    }

    public function issueDocument(ShipmentHeader $shipment, ApplyDraftShipmentPricingService $pricingService, OperationalPeriod $operationalPeriod): JsonResponse
    {
        $operationalPeriod->ensureOpen($shipment->document_date?->toDateString(), '出荷日');
        abort_if($shipment->status !== 'draft', 422, '出荷ドラフトだけ発伝できます。');
        $shipment = $pricingService->apply($shipment, '出荷伝票作成時の価格適用');

        if ($shipment->document_issued_at === null) {
            $shipment->forceFill(['document_issued_at' => now()])->save();
        }

        return $this->ok([
            'shipment' => $this->serializeShipment($shipment->refresh()->load(['customer', 'sourceShipmentPick.shipmentInstruction.lines.salesOrder', 'sourceShipmentInstruction.lines.salesOrder', 'lines.product.capacityUnit', 'lines.unit', 'lines.confirmedCapacityUnit'])),
        ]);
    }

    public function store(
        StoreShipmentRequest $request,
        CreateDraftShipmentService $createDraftShipmentService,
        CreateDraftShipmentFromPickService $createDraftShipmentFromPickService,
    ): JsonResponse {
        $validated = $request->validated();
        if (isset($validated['document_date'])) {
            app(OperationalPeriod::class)->ensureOpen($validated['document_date'], '出荷日');
        }

        if (isset($validated['source_shipment_pick_id'])) {
            $shipment = $createDraftShipmentFromPickService->create(new CreateDraftShipmentFromPickData(
                shipmentPickId: (int) $validated['source_shipment_pick_id'],
                documentDate: $validated['document_date'] ?? null,
                billingTargetDate: $validated['billing_target_date'] ?? null,
                liquorTaxTransferDate: $validated['liquor_tax_transfer_date'] ?? null,
                note: $validated['note'] ?? null,
                reason: $validated['reason'] ?? null,
            ));
        } else {
            $shipment = $createDraftShipmentService->create(new CreateDraftShipmentData(
                customerId: (int) $validated['customer_id'],
                documentDate: $validated['document_date'],
                orderDate: $validated['order_date'] ?? null,
                scheduledShipmentDate: $validated['scheduled_shipment_date'] ?? null,
                billingTargetDate: $validated['billing_target_date'] ?? null,
                liquorTaxTransferDate: $validated['liquor_tax_transfer_date'] ?? null,
                note: $validated['note'] ?? null,
                reason: $validated['reason'] ?? null,
                lines: array_map(
                    fn (array $line): CreateDraftShipmentLineData => new CreateDraftShipmentLineData(
                        productId: (int) $line['product_id'],
                        quantity: $line['quantity'],
                        unitId: (int) $line['unit_id'],
                        note: $line['note'] ?? null,
                    ),
                    $validated['lines'],
                ),
            ));
        }

        return $this->created([
            'shipment' => $this->serializeShipment($shipment),
        ]);
    }

    public function price(
        ShipmentActionReasonRequest $request,
        ShipmentHeader $shipment,
        ApplyDraftShipmentPricingService $service,
        OperationalPeriod $operationalPeriod,
    ): JsonResponse {
        $operationalPeriod->ensureOpen($shipment->document_date?->toDateString(), '出荷日');
        $priced = $service->apply($shipment, $request->validated('reason'));

        return $this->ok([
            'shipment' => $this->serializeShipment($priced),
        ]);
    }

    public function confirm(
        ShipmentActionReasonRequest $request,
        ShipmentHeader $shipment,
        ConfirmShipmentService $service,
        OperationalPeriod $operationalPeriod,
    ): JsonResponse {
        $operationalPeriod->ensureOpen($shipment->document_date?->toDateString(), '出荷日');
        $confirmed = $service->confirm($shipment, $request->validated('reason'));

        return $this->ok([
            'shipment' => $this->serializeShipment($confirmed),
        ]);
    }

    public function printAndConfirm(
        ShipmentActionReasonRequest $request,
        ShipmentHeader $shipment,
        ApplyDraftShipmentPricingService $pricingService,
        ConfirmShipmentService $confirmService,
        OperationalPeriod $operationalPeriod,
    ): JsonResponse {
        $reason = $request->validated('reason') ?: '出荷伝票の印刷による出荷確定';
        $operationalPeriod->ensureOpen($shipment->document_date?->toDateString(), '出荷日');
        $priced = $pricingService->apply($shipment, $reason);
        $confirmed = $confirmService->confirm($priced, $reason);

        return $this->ok(['shipment' => $this->serializeShipment($confirmed)]);
    }

    public function cancel(
        CancelShipmentRequest $request,
        ShipmentHeader $shipment,
        CancelShipmentService $service,
        OperationalPeriod $operationalPeriod,
    ): JsonResponse {
        $operationalPeriod->ensureOpen($shipment->document_date?->toDateString(), '出荷日');
        $cancelled = $service->cancel($shipment, $request->validated('reason'));

        return $this->ok([
            'shipment' => $this->serializeShipment($cancelled),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeShipment(ShipmentHeader $shipment): array
    {
        $instruction = $shipment->sourceShipmentPick?->shipmentInstruction ?? $shipment->sourceShipmentInstruction;
        $salesOrder = $instruction?->lines->first()?->salesOrder;

        return [
            'id' => $shipment->id,
            'document_number' => $shipment->document_number,
            'status' => $shipment->status,
            'shipping_status_key' => $this->shippingStatusKey($shipment),
            'shipping_status_label' => $this->shippingStatusLabel($shipment),
            'customer_id' => $shipment->customer_id,
            'customer_name' => $shipment->customer?->name,
            'document_date' => $shipment->document_date?->toDateString(),
            'document_issued_at' => $shipment->document_issued_at?->toISOString(),
            'document_issued' => $shipment->document_issued_at !== null,
            'order_date' => $shipment->order_date?->toDateString(),
            'scheduled_shipment_date' => $shipment->scheduled_shipment_date?->toDateString(),
            'billing_target_date' => $shipment->billing_target_date?->toDateString(),
            'liquor_tax_transfer_date' => $shipment->liquor_tax_transfer_date?->toDateString(),
            'source_shipment_pick_id' => $shipment->source_shipment_pick_id,
            'source_shipment_instruction_id' => $shipment->source_shipment_instruction_id,
            'source_shipment_pick_number' => $shipment->sourceShipmentPick?->pick_number,
            'shipment_instruction_number' => $instruction?->instruction_number,
            'sales_order_number' => $salesOrder?->order_number,
            'cancelled_reason' => $shipment->cancelled_reason,
            'cancelled_at' => $shipment->cancelled_at?->toISOString(),
            'invoiced' => $shipment->invoiceLines()
                ->whereHas('invoiceHeader', fn ($query) => $query
                    ->where('document_type', '!=', 'credit_memo')
                    ->where('status', '!=', 'cancelled'))
                ->exists(),
            'lines' => $shipment->lines
                ->map(function (ShipmentLine $line): array {
                    $capacityValue = $line->confirmed_capacity_value ?? $line->product?->capacity_value;
                    $capacityUnit = $line->confirmed_capacity_unit_id !== null
                        ? $line->confirmedCapacityUnit
                        : $line->product?->capacityUnit;

                    return [
                        'id' => $line->id,
                        'line_no' => $line->line_no,
                        'product_id' => $line->product_id,
                        'product_name' => $line->confirmed_display_name
                            ?: $line->product?->display_name
                            ?: $line->confirmed_product_name
                            ?: $line->product?->name,
                        'quantity' => $line->quantity,
                        'unit_id' => $line->unit_id,
                        'unit_code' => $line->unit?->code,
                        'unit_name' => $line->unit?->name,
                        'source_shipment_pick_line_id' => $line->source_shipment_pick_line_id,
                        'draft_unit_price' => $line->draft_unit_price,
                        'draft_price_source' => $line->draft_price_source,
                        'confirmed_product_code' => $line->confirmed_product_code,
                        'confirmed_quantity' => $line->confirmed_quantity,
                        'confirmed_unit_price' => $line->confirmed_unit_price,
                        'confirmed_consumption_tax_rate' => $line->confirmed_consumption_tax_rate,
                        'confirmed_liquor_tax_per_kl' => $line->confirmed_liquor_tax_per_kl,
                        'confirmed_liquor_tax_estimated_amount' => $line->confirmed_liquor_tax_estimated_amount,
                        'capacity_value' => $capacityValue,
                        'capacity_unit_id' => $capacityUnit?->id,
                        'capacity_unit_code' => $capacityUnit?->code,
                        'capacity_unit_name' => $capacityUnit?->symbol ?: $capacityUnit?->name,
                        'confirmed_at' => $line->confirmed_at?->toISOString(),
                        'note' => $line->note,
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeShipmentHistory(ShipmentHeader $shipment): array
    {
        $data = $this->serializeShipment($shipment);
        $activeInvoiceHeaders = $shipment->invoiceLines
            ->map(fn ($line) => $line->invoiceHeader)
            ->filter(fn ($invoice) => $invoice !== null && $invoice->document_type !== 'credit_memo' && $invoice->status !== 'cancelled')
            ->unique('id')
            ->values();

        $data['invoice_numbers'] = $activeInvoiceHeaders
            ->pluck('invoice_number')
            ->values()
            ->all();
        $data['invoice_statuses'] = $activeInvoiceHeaders
            ->map(fn ($invoice) => $invoice->status)
            ->unique()
            ->values()
            ->all();
        $data['invoice_created'] = $activeInvoiceHeaders->isNotEmpty();
        $data['line_count'] = $shipment->lines->count();

        return $data;
    }

    private function shippingStatusKey(ShipmentHeader $shipment): string
    {
        if ($shipment->status === 'confirmed') {
            return 'completed';
        }

        if ($shipment->status === 'draft') {
            return $shipment->document_issued_at === null ? 'ready' : 'issued';
        }

        return $shipment->status;
    }

    private function shippingStatusLabel(ShipmentHeader $shipment): string
    {
        return match ($this->shippingStatusKey($shipment)) {
            'ready' => '発伝待ち',
            'issued' => '伝票作成済',
            'completed' => '出荷済み',
            'cancelled' => '取消',
            default => $shipment->status,
        };
    }
}
