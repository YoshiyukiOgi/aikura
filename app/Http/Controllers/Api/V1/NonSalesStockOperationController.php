<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreNonSalesStockOperationRequest;
use App\Models\NonSalesStockOperationHeader;
use App\Models\NonSalesStockOperationLine;
use App\Services\Inventory\CreateNonSalesStockOperationData;
use App\Services\Inventory\CreateNonSalesStockOperationLineData;
use App\Services\Inventory\CreateNonSalesStockOperationService;
use App\Services\Inventory\CancelNonSalesStockOperationService;
use App\Services\Inventory\EnsureStockPeriodIsOpenService;
use App\Services\Inventory\EvaluateRepackagingAlcoholWarningService;
use App\Services\Inventory\UpdateNonSalesStockOperationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NonSalesStockOperationController extends ApiController
{
    public function __construct(private readonly EnsureStockPeriodIsOpenService $ensureStockPeriodIsOpenService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['nullable', 'string', 'max:100'],
            'operation_date' => ['nullable', 'date'],
            'operation_type' => ['nullable', 'string', 'max:100'],
        ]);

        $operations = NonSalesStockOperationHeader::query()
            ->with(['lines.productionLot', 'lines.stockLocation', 'revisions.createdBy'])
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('stock_lot_monthly_balances as closed_periods')
                    ->whereIn('closed_periods.status', ['confirmed', 'closed'])
                    ->whereColumn('closed_periods.period_start', '<=', 'non_sales_stock_operation_headers.operation_date')
                    ->whereColumn('closed_periods.period_end', '>=', 'non_sales_stock_operation_headers.operation_date');
            })
            ->when($validated['id'] ?? null, function ($query, string $id): void {
                $query->where(function ($inner) use ($id): void {
                    if (ctype_digit($id)) {
                        $inner->orWhere('non_sales_stock_operation_headers.id', (int) $id);
                    }

                    $inner->orWhere('non_sales_stock_operation_headers.operation_number', 'like', '%'.$id.'%');
                });
            })
            ->when($validated['operation_date'] ?? null, fn ($query, string $date) => $query->whereDate('non_sales_stock_operation_headers.operation_date', $date))
            ->when($validated['operation_type'] ?? null, fn ($query, string $type) => $query->where('non_sales_stock_operation_headers.operation_type', $type))
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (NonSalesStockOperationHeader $operation): array => $this->serializeOperation($operation))
            ->values()
            ->all();

        return $this->ok(['non_sales_stock_operations' => $operations]);
    }

    public function show(NonSalesStockOperationHeader $nonSalesStockOperationHeader): JsonResponse
    {
        return $this->ok([
            'non_sales_stock_operation' => $this->serializeOperation($nonSalesStockOperationHeader->load(['lines.productionLot', 'lines.stockLocation', 'revisions.createdBy'])),
        ]);
    }

    public function store(StoreNonSalesStockOperationRequest $request, CreateNonSalesStockOperationService $service): JsonResponse
    {
        $validated = $request->validated();

        $operation = $service->create($this->operationData($validated));

        return $this->created(['non_sales_stock_operation' => $this->serializeOperation($operation)]);
    }

    public function checkRepackagingAlcohol(Request $request, EvaluateRepackagingAlcoholWarningService $service): JsonResponse
    {
        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.production_lot_id' => ['required', 'integer', 'exists:production_lots,id'],
            'lines.*.quantity' => ['required', 'numeric', 'not_in:0'],
        ]);

        return $this->ok(['alcohol_check' => $service->evaluate($validated['lines'])]);
    }

    public function update(StoreNonSalesStockOperationRequest $request, NonSalesStockOperationHeader $nonSalesStockOperationHeader, UpdateNonSalesStockOperationService $service): JsonResponse
    {
        $operation = $service->update($nonSalesStockOperationHeader, $this->operationData($request->validated()));

        return $this->ok(['non_sales_stock_operation' => $this->serializeOperation($operation)]);
    }

    /** @param array<string, mixed> $validated */
    private function operationData(array $validated): CreateNonSalesStockOperationData
    {
        return new CreateNonSalesStockOperationData(
            operationType: $validated['operation_type'],
            operationDate: $validated['operation_date'],
            reason: $validated['reason'] ?? '',
            lines: array_map(
                fn (array $line): CreateNonSalesStockOperationLineData => new CreateNonSalesStockOperationLineData(
                    productionLotId: (int) $line['production_lot_id'],
                    stockLocationId: (int) $line['stock_location_id'],
                    quantity: $line['quantity'],
                    productId: isset($line['product_id']) ? (int) $line['product_id'] : null,
                    sourceSalesReturnLineId: isset($line['source_sales_return_line_id']) ? (int) $line['source_sales_return_line_id'] : null,
                    lotCode: $line['lot_code'] ?? null,
                    reason: $line['reason'] ?? null,
                    note: $line['note'] ?? null,
                ),
                $validated['lines'],
            ),
            sourceSalesReturnHeaderId: isset($validated['source_sales_return_header_id'])
                ? (int) $validated['source_sales_return_header_id']
                : null,
            note: $validated['note'] ?? null,
            alcoholWarningAcknowledged: (bool) ($validated['alcohol_warning_acknowledged'] ?? false),
        );
    }

    public function cancel(Request $request, NonSalesStockOperationHeader $nonSalesStockOperationHeader, CancelNonSalesStockOperationService $service): JsonResponse
    {
        $v = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        return $this->ok(['non_sales_stock_operation' => $this->serializeOperation($service->cancel($nonSalesStockOperationHeader, $v['reason']))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOperation(NonSalesStockOperationHeader $operation): array
    {
        return [
            'id' => $operation->id,
            'operation_number' => $operation->operation_number,
            'revision_no' => $operation->revision_no,
            'status' => $operation->status,
            'operation_type' => $operation->operation_type,
            'operation_date' => $operation->operation_date?->toDateString(),
            'source_sales_return_header_id' => $operation->source_sales_return_header_id,
            'confirmed_at' => $operation->confirmed_at?->toISOString(),
            'cancelled_at' => $operation->cancelled_at?->toISOString(),
            'cancelled_reason' => $operation->cancelled_reason,
            'reason' => $operation->reason,
            'note' => $operation->note,
            'is_period_open' => $this->ensureStockPeriodIsOpenService->isOpen($operation->operation_date->toDateString()),
            'is_editable' => $operation->status === 'confirmed'
                && $operation->source_sales_return_header_id === null
                && $this->ensureStockPeriodIsOpenService->isOpen($operation->operation_date->toDateString()),
            'lines' => $operation->lines
                ->map(fn (NonSalesStockOperationLine $line): array => [
                    'id' => $line->id,
                    'line_no' => $line->line_no,
                    'source_sales_return_line_id' => $line->source_sales_return_line_id,
                    'stock_movement_id' => $line->stock_movement_id,
                    'stock_location_id' => $line->stock_location_id,
                    'unit_id' => $line->unit_id,
                    'quantity' => $line->quantity,
                    'production_lot_id' => $line->production_lot_id,
                    'lot_code' => $line->lot_code,
                    'lot_name' => $line->productionLot?->display_name,
                    'stock_location_name' => $line->stockLocation?->name,
                    'reason' => $line->reason,
                    'note' => $line->note,
                ])
                ->values()
                ->all(),
            'revisions' => $operation->revisions
                ->map(fn ($revision): array => [
                    'revision_no' => $revision->revision_no,
                    'action' => $revision->action,
                    'operation_type' => $revision->operation_type,
                    'operation_date' => $revision->operation_date?->toDateString(),
                    'reason' => $revision->reason,
                    'lines' => $revision->lines,
                    'created_by_name' => $revision->createdBy?->name,
                    'created_at' => $revision->created_at?->toISOString(),
                ])->values()->all(),
        ];
    }
}
