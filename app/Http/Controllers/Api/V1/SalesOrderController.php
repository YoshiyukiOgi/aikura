<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\CancelSalesOrderRequest;
use App\Http\Requests\Api\V1\IndexSalesOrderRequest;
use App\Http\Requests\Api\V1\OverrideSalesOrderLinePriceRequest;
use App\Http\Requests\Api\V1\SalesOrderPricingRequest;
use App\Http\Requests\Api\V1\StoreSalesOrderRequest;
use App\Http\Requests\Api\V1\UpdateSalesOrderRequest;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\PriceReviewTask;
use App\Services\SalesOrder\CancelSalesOrderService;
use App\Services\SalesOrder\ApplySalesOrderPricingService;
use App\Services\SalesOrder\CreateSalesOrderData;
use App\Services\SalesOrder\CreateSalesOrderLineData;
use App\Services\SalesOrder\CreateSalesOrderService;
use App\Services\SalesOrder\OverrideSalesOrderLinePriceService;
use App\Services\SalesOrder\ReapplySalesOrderLinePricingService;
use App\Services\SalesOrder\UpdateSalesOrderService;
use App\Services\SalesOrder\MarkSalesOrderAwaitingShipmentInstructionService;
use App\Services\ShipmentInstruction\CreateShipmentInstructionData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionLineData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SalesOrderController extends ApiController
{
    public function index(IndexSalesOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $status = $validated['status'] ?? null;
        $salesOrders = SalesOrder::query()
            ->with(['customer', 'lines.product', 'lines.unit'])
            ->when(isset($validated['customer_id']), fn ($query) => $query->where('customer_id', $validated['customer_id']))
            ->when($status === 'received', fn ($query) => $query->where('status', 'received')->whereNull('shipment_returned_at'))
            ->when($status === 'shipment_returned', fn ($query) => $query->where('status', 'received')->whereNotNull('shipment_returned_at'))
            ->when(! $status, fn ($query) => $query->where('status', 'received'))
            ->when(isset($validated['awaiting_shipment_instruction']), fn ($query) => $query->where('awaiting_shipment_instruction', $validated['awaiting_shipment_instruction']))
            ->when(isset($validated['order_date_from']), fn ($query) => $query->whereDate('order_date', '>=', $validated['order_date_from']))
            ->when(isset($validated['order_date_to']), fn ($query) => $query->whereDate('order_date', '<=', $validated['order_date_to']))
            ->when(isset($validated['q']), function ($query) use ($validated): void {
                $term = $validated['q'];
                $query->where(function ($query) use ($term): void {
                    $query->where('order_number', 'like', "%{$term}%")
                        ->orWhere('customer_order_number', 'like', "%{$term}%")
                        ->orWhereHas('customer', fn ($query) => $query->where('name', 'like', "%{$term}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate((int) ($validated['per_page'] ?? 25));

        return $this->ok([
            'sales_orders' => collect($salesOrders->items())
                ->map(fn (SalesOrder $salesOrder): array => $this->serializeSalesOrder($salesOrder))
                ->values()
                ->all(),
            'pagination' => [
                'current_page' => $salesOrders->currentPage(),
                'last_page' => $salesOrders->lastPage(),
                'per_page' => $salesOrders->perPage(),
                'total' => $salesOrders->total(),
            ],
        ]);
    }

    public function show(SalesOrder $salesOrder): JsonResponse
    {
        return $this->ok([
            'sales_order' => $this->serializeSalesOrder(
                $salesOrder->load(['customer', 'lines.product', 'lines.unit']),
            ),
        ]);
    }

    public function store(StoreSalesOrderRequest $request, CreateSalesOrderService $service, CreateShipmentInstructionService $instructionService): JsonResponse
    {
        $validated = $request->validated();

        $result = DB::transaction(function () use ($validated, $service, $instructionService): array {
        $salesOrder = $service->create(new CreateSalesOrderData(
            customerId: (int) $validated['customer_id'],
            orderDate: $validated['order_date'],
            requestedShipmentDate: $validated['requested_shipment_date'] ?? null,
            requestedDeliveryDate: $validated['requested_delivery_date'] ?? null,
            billingTargetDate: $validated['billing_target_date'] ?? null,
            customerOrderNumber: $validated['customer_order_number'] ?? null,
            sourceType: $validated['source_type'] ?? null,
            sourceReference: $validated['source_reference'] ?? null,
            note: $validated['note'] ?? null,
            workNote: $validated['work_note'] ?? null,
            reason: $validated['reason'] ?? null,
            applyPricing: true,
            awaitingShipmentInstruction: (bool) ($validated['awaiting_shipment_instruction'] ?? false),
            lines: array_map(
                fn (array $line): CreateSalesOrderLineData => new CreateSalesOrderLineData(
                    productId: (int) $line['product_id'],
                    quantity: $line['quantity'],
                    unitId: (int) $line['unit_id'],
                    note: $line['note'] ?? null,
                ),
                $validated['lines'],
            ),
        ));

        $instruction = null;
        if ((bool) ($validated['auto_release_to_shipping'] ?? false)) {
            $instruction = $instructionService->create(new CreateShipmentInstructionData(
                instructionDate: now()->toDateString(),
                scheduledShipmentDate: $salesOrder->requested_shipment_date?->toDateString()
                    ?? $salesOrder->requested_delivery_date?->toDateString()
                    ?? $salesOrder->order_date?->toDateString(),
                note: '受注登録時に自動作成',
                reason: '受注登録と同時に出荷作業へ送付',
                lines: $salesOrder->lines->map(
                    fn (SalesOrderLine $line): CreateShipmentInstructionLineData => new CreateShipmentInstructionLineData(
                        salesOrderLineId: $line->id,
                        quantity: (string) $line->remaining_quantity,
                        note: $line->note,
                    ),
                )->all(),
            ));
        }

        return [$salesOrder->refresh()->load(['customer', 'lines.product', 'lines.unit']), $instruction];
        });

        return $this->created([
            'sales_order' => $this->serializeSalesOrder($result[0]),
            'shipment_instruction_id' => $result[1]?->id,
        ]);
    }

    public function cancel(
        CancelSalesOrderRequest $request,
        SalesOrder $salesOrder,
        CancelSalesOrderService $service,
    ): JsonResponse {
        $cancelled = $service->cancel($salesOrder, $request->validated('reason'));

        return $this->ok([
            'sales_order' => $this->serializeSalesOrder($cancelled),
        ]);
    }

    public function update(UpdateSalesOrderRequest $request, SalesOrder $salesOrder, UpdateSalesOrderService $service): JsonResponse
    {
        $validated = $request->validated();

        return $this->ok(['sales_order' => $this->serializeSalesOrder($service->update(
            $salesOrder,
            attributes: [
                'requested_shipment_date' => $validated['requested_shipment_date'] ?? null,
                'requested_delivery_date' => $validated['requested_delivery_date'] ?? null,
                'customer_order_number' => $validated['customer_order_number'] ?? null,
                'note' => $validated['note'] ?? null,
                'work_note' => $validated['work_note'] ?? null,
            ],
            lines: $validated['lines'],
            reason: $validated['reason'] ?? null,
        ))]);
    }

    public function releaseToShipping(SalesOrder $salesOrder, CreateShipmentInstructionService $service): JsonResponse
    {
        $salesOrder->load('lines');
        $lines = $salesOrder->lines
            ->filter(fn (SalesOrderLine $line): bool => bccomp((string) $line->remaining_quantity, '0.0000', 4) > 0)
            ->map(fn (SalesOrderLine $line): CreateShipmentInstructionLineData => new CreateShipmentInstructionLineData(
                salesOrderLineId: $line->id,
                quantity: (string) $line->remaining_quantity,
                note: $line->note,
            ))
            ->values()
            ->all();

        $instruction = $service->create(new CreateShipmentInstructionData(
            instructionDate: now()->toDateString(),
            scheduledShipmentDate: $salesOrder->requested_shipment_date?->toDateString()
                ?? $salesOrder->requested_delivery_date?->toDateString()
                ?? $salesOrder->order_date?->toDateString(),
            note: '受注画面から即時出荷指示',
            reason: '受注画面から即時出荷指示',
            lines: $lines,
        ));

        return $this->created([
            'sales_order' => $this->serializeSalesOrder(
                $salesOrder->refresh()->load(['customer', 'lines.product', 'lines.unit']),
            ),
            'shipment_instruction_id' => $instruction->id,
        ]);
    }

    public function deferShipmentInstruction(
        SalesOrder $salesOrder,
        MarkSalesOrderAwaitingShipmentInstructionService $service,
    ): JsonResponse {
        return $this->ok([
            'sales_order' => $this->serializeSalesOrder($service->mark($salesOrder)),
        ]);
    }

    public function resumeFromShipmentInstruction(
        SalesOrder $salesOrder,
        MarkSalesOrderAwaitingShipmentInstructionService $service,
    ): JsonResponse {
        return $this->ok([
            'sales_order' => $this->serializeSalesOrder($service->unmark($salesOrder)),
        ]);
    }

    public function price(
        SalesOrderPricingRequest $request,
        SalesOrder $salesOrder,
        ApplySalesOrderPricingService $service,
    ): JsonResponse {
        return $this->ok([
            'sales_order' => $this->serializeSalesOrder($service->apply($salesOrder, $request->validated('reason'))),
        ]);
    }

    public function overrideLinePrice(
        OverrideSalesOrderLinePriceRequest $request,
        SalesOrder $salesOrder,
        SalesOrderLine $salesOrderLine,
        OverrideSalesOrderLinePriceService $service,
    ): JsonResponse {
        $validated = $request->validated();

        return $this->ok([
            'sales_order' => $this->serializeSalesOrder($service->override(
                $salesOrder,
                $salesOrderLine,
                $validated['unit_price'],
                $validated['reason'],
                (bool) ($validated['save_as_customer_price'] ?? false),
            )),
        ]);
    }

    public function reapplyLinePrice(
        SalesOrderPricingRequest $request,
        SalesOrder $salesOrder,
        SalesOrderLine $salesOrderLine,
        ReapplySalesOrderLinePricingService $service,
    ): JsonResponse {
        return $this->ok([
            'sales_order' => $this->serializeSalesOrder($service->reapply(
                $salesOrder,
                $salesOrderLine,
                $request->validated('reason'),
            )),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSalesOrder(SalesOrder $salesOrder): array
    {
        return [
            'id' => $salesOrder->id,
            'order_number' => $salesOrder->order_number,
            'status' => $salesOrder->status,
            'display_status' => $salesOrder->shipment_returned_at ? 'shipment_returned' : $salesOrder->status,
            'shipment_returned_at' => $salesOrder->shipment_returned_at?->toISOString(),
            'shipment_returned_reason' => $salesOrder->shipment_returned_reason,
            'awaiting_shipment_instruction' => $salesOrder->awaiting_shipment_instruction,
            'customer_id' => $salesOrder->customer_id,
            'customer_name' => $salesOrder->customer?->name,
            'order_date' => $salesOrder->order_date?->toDateString(),
            'requested_shipment_date' => $salesOrder->requested_shipment_date?->toDateString(),
            'requested_delivery_date' => $salesOrder->requested_delivery_date?->toDateString(),
            'billing_target_date' => $salesOrder->billing_target_date?->toDateString(),
            'customer_order_number' => $salesOrder->customer_order_number,
            'note' => $salesOrder->note,
            'work_note' => $salesOrder->work_note,
            'cancelled_reason' => $salesOrder->cancelled_reason,
            'cancelled_at' => $salesOrder->cancelled_at?->toISOString(),
            'lines' => $salesOrder->lines
                ->map(fn (SalesOrderLine $line): array => [
                    'id' => $line->id,
                    'line_no' => $line->line_no,
                    'product_id' => $line->product_id,
                    'product_name' => $line->product?->name,
                    'quantity' => $line->quantity,
                    'remaining_quantity' => $line->remaining_quantity,
                    'unit_id' => $line->unit_id,
                    'unit_code' => $line->unit?->code,
                    'unit_name' => $line->unit?->name,
                    'unit_price' => $line->unit_price,
                    'price_list_id' => $line->price_list_id,
                    'price_rule_id' => $line->price_rule_id,
                    'price_source' => $line->price_source,
                    'price_reason' => $line->price_reason,
                    'price_review_task' => $this->pendingPriceReviewTask($salesOrder, $line),
                    'priced_at' => $line->priced_at?->toISOString(),
                    'note' => $line->note,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pendingPriceReviewTask(SalesOrder $salesOrder, SalesOrderLine $line): ?array
    {
        $task = PriceReviewTask::query()
            ->where('status', PriceReviewTask::STATUS_PENDING)
            ->where('customer_id', $salesOrder->customer_id)
            ->where('product_id', $line->product_id)
            ->orderByDesc('id')
            ->first();

        if (! $task) {
            return null;
        }

        return [
            'id' => $task->id,
            'message' => $task->message,
            'old_reference_price' => $task->old_reference_price,
            'new_reference_price' => $task->new_reference_price,
            'current_individual_price' => $task->current_individual_price,
            'status' => $task->status,
        ];
    }
}
