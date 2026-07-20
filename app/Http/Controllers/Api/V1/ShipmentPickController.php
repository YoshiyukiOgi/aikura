<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\CancelShipmentPickRequest;
use App\Http\Requests\Api\V1\StoreShipmentPickRequest;
use App\Models\ProductionLot;
use App\Models\ShipmentHeader;
use App\Models\ShipmentInstruction;
use App\Models\ShipmentInstructionLine;
use App\Models\ShipmentLine;
use App\Models\ShipmentLotAllocation;
use App\Models\ShipmentPick;
use App\Models\ShipmentPickLine;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Services\Inventory\EvaluateLotProductCompatibilityService;
use App\Services\Inventory\LotVisibilityPolicy;
use App\Services\Shipment\CreateDraftShipmentFromInstructionService;
use App\Services\ShipmentPicking\CancelShipmentPickService;
use App\Services\ShipmentPicking\PickShipmentInstructionData;
use App\Services\ShipmentPicking\PickShipmentInstructionLineData;
use App\Services\ShipmentPicking\PickShipmentInstructionService;
use App\Services\ShipmentPicking\SavePickingLotAllocationsService;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShipmentPickController extends ApiController
{
    public function index(): JsonResponse
    {
        $picks = ShipmentPick::query()
            ->with(['shipmentInstruction.customer', 'shipmentInstruction.lines.salesOrder', 'stockLocation', 'shipmentHeader', 'lines.shipmentInstructionLine', 'lines.product', 'lines.unit', 'lines.lotAllocations.productionLot', 'lines.lotAllocations.approvalRequest'])
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (ShipmentPick $pick): array => $this->serializePick($pick))
            ->values()
            ->all();

        return $this->ok([
            'shipment_picks' => $picks,
        ]);
    }

    public function show(ShipmentPick $shipmentPick): JsonResponse
    {
        return $this->ok([
            'shipment_pick' => $this->serializePick(
                $shipmentPick->load(['shipmentInstruction.customer', 'shipmentInstruction.lines.salesOrder', 'stockLocation', 'shipmentHeader', 'lines.shipmentInstructionLine', 'lines.product', 'lines.unit', 'lines.lotAllocations.productionLot', 'lines.lotAllocations.approvalRequest']),
            ),
        ]);
    }

    public function store(
        StoreShipmentPickRequest $request,
        PickShipmentInstructionService $service,
    ): JsonResponse {
        $validated = $request->validated();

        $pick = $service->pick(new PickShipmentInstructionData(
            shipmentInstructionId: (int) $validated['shipment_instruction_id'],
            pickDate: $validated['pick_date'],
            stockLocationId: isset($validated['stock_location_id']) ? (int) $validated['stock_location_id'] : null,
            note: $validated['note'] ?? null,
            reason: $validated['reason'] ?? null,
            lines: array_map(
                fn (array $line): PickShipmentInstructionLineData => new PickShipmentInstructionLineData(
                    shipmentInstructionLineId: (int) $line['shipment_instruction_line_id'],
                    quantity: $line['quantity'],
                    note: $line['note'] ?? null,
                ),
                $validated['lines'],
            ),
        ));

        return $this->created([
            'shipment_pick' => $this->serializePick($pick),
        ]);
    }

    public function cancel(
        CancelShipmentPickRequest $request,
        ShipmentPick $shipmentPick,
        CancelShipmentPickService $service,
    ): JsonResponse {
        $cancelled = $service->cancel($shipmentPick, $request->validated('reason'));

        return $this->ok([
            'shipment_pick' => $this->serializePick($cancelled),
        ]);
    }

    public function lineLots(
        ShipmentInstruction $shipmentInstruction,
        ShipmentInstructionLine $shipmentInstructionLine,
        CreateDraftShipmentFromInstructionService $draftService,
        EvaluateLotProductCompatibilityService $compatibilityService,
        LotVisibilityPolicy $visibility,
    ): JsonResponse {
        $this->abortIfLineDoesNotBelongToInstruction($shipmentInstruction, $shipmentInstructionLine);

        $shipment = $draftService->create($shipmentInstruction);
        $shipmentLine = $this->shipmentLineForInstructionLine($shipment, $shipmentInstructionLine);
        $stockLocation = $this->stockLocationForInstruction($shipmentInstruction);
        $currentAllocations = $this->currentAllocationsForInstructionLine($shipment, $shipmentInstructionLine);
        $currentByLot = $currentAllocations
            ->groupBy('production_lot_id')
            ->map(fn ($rows): string => bcadd((string) $rows->sum('quantity'), '0', 4));
        $currentAllocationByLot = $currentAllocations->keyBy('production_lot_id');
        $recentAllocation = $this->recentAllocationForLine($shipmentInstruction, $shipmentInstructionLine, $shipment->id);
        $recentByLot = collect($recentAllocation['allocations'])
            ->mapWithKeys(fn (array $allocation): array => [(int) $allocation['production_lot_id'] => $allocation['quantity']]);

        $balances = StockMovement::query()
            ->selectRaw('production_lot_id, stock_location_id, unit_id, COALESCE(SUM(quantity), 0) as physical_quantity')
            ->whereNotNull('production_lot_id')
            ->where('stock_location_id', $stockLocation->id)
            ->where('unit_id', $shipmentInstructionLine->unit_id)
            ->whereIn('status', ['confirmed', 'closed'])
            ->whereNull('cancelled_at')
            ->groupBy('production_lot_id', 'stock_location_id', 'unit_id')
            ->get()
            ->map(function (object $row): object {
                $allocated = ShipmentLotAllocation::query()
                    ->where('production_lot_id', (int) $row->production_lot_id)
                    ->where('stock_location_id', (int) $row->stock_location_id)
                    ->where('unit_id', (int) $row->unit_id)
                    ->where('status', 'allocated')
                    ->whereNull('cancelled_at')
                    ->sum('quantity');

                return (object) [
                    'productionLotId' => (int) $row->production_lot_id,
                    'stockLocationId' => (int) $row->stock_location_id,
                    'unitId' => (int) $row->unit_id,
                    'availableQuantity' => bcsub(bcadd((string) $row->physical_quantity, '0', 4), bcadd((string) $allocated, '0', 4), 4),
                ];
            })
            ->values();

        $currentByLot->each(function (string $quantity, int $lotId) use ($balances, $stockLocation, $shipmentInstructionLine): void {
            if ($balances->contains('productionLotId', $lotId)) {
                return;
            }

            $balances->push((object) [
                'productionLotId' => $lotId,
                'stockLocationId' => $stockLocation->id,
                'unitId' => $shipmentInstructionLine->unit_id,
                'availableQuantity' => bcsub('0.0000', $quantity, 4),
            ]);
        });

        $lotIds = $balances->pluck('productionLotId')
            ->merge($currentAllocations->pluck('production_lot_id'))
            ->unique()
            ->values();

        $lots = ProductionLot::query()
            ->with(['capacityUnit'])
            ->whereIn('id', $lotIds)
            ->get()
            ->keyBy('id');

        $candidates = $balances
            ->map(function ($balance) use ($lots, $currentByLot, $currentAllocationByLot, $recentByLot, $shipmentLine, $compatibilityService, $visibility): ?array {
                $lot = $lots->get($balance->productionLotId);
                if (! $lot) {
                    return null;
                }
                $current = $currentByLot->get($lot->id, '0.0000');
                $available = bcadd($balance->availableQuantity, $current, 4);
                $hasCurrentAllocation = bccomp($current, '0.0000', 4) > 0;
                $stockComparison = bccomp($available, '0.0000', 4);
                $stockStatus = $stockComparison < 0 ? 'negative' : ($stockComparison === 0 ? 'zero' : 'available');
                $isActive = $lot->is_active && $lot->status === 'active';

                if ($stockStatus === 'zero' && ! $hasCurrentAllocation && $visibility->hideZeroStockLots()) {
                    return null;
                }

                $compatibility = $compatibilityService->evaluate($shipmentLine->product, $lot, $shipmentLine->unit_id);
                $selectable = $isActive
                    && $compatibility['selectable']
                    && ($stockStatus === 'available' || $hasCurrentAllocation);
                if ($stockStatus === 'available' && ! $selectable && ! $hasCurrentAllocation) {
                    return null;
                }
                $alcohol = $compatibility['alcohol'];
                $currentAllocation = $currentAllocationByLot->get($lot->id);

                return [
                    'production_lot_id' => $lot->id,
                    'lot_code' => $lot->lot_code,
                    'display_name' => $lot->display_name,
                    'production_date' => $lot->production_date?->toDateString(),
                    'bottling_date' => $lot->bottling_date?->toDateString(),
                    'rice_variety' => $lot->rice_variety,
                    'rice_polishing_ratio' => $lot->rice_polishing_ratio,
                    'production_method' => $lot->production_method,
                    'capacity_value' => $lot->capacity_value,
                    'capacity_unit_id' => $lot->capacity_unit_id,
                    'capacity_unit_name' => $lot->capacityUnit?->name,
                    'alcohol_percentage' => $lot->alcohol_percentage,
                    'analysis_status' => $lot->analysis_status,
                    'compatibility_status' => $compatibility['status'],
                    'approval_required' => $compatibility['approval_required'],
                    'alcohol_compliance_status' => $alcohol['status'],
                    'standard_alcohol_percentage' => $alcohol['standard'],
                    'allowed_alcohol_min' => $alcohol['min'],
                    'allowed_alcohol_max' => $alcohol['max'],
                    'available_quantity' => $available,
                    'current_quantity' => $current,
                    'stock_status' => $stockStatus,
                    'selectable' => $selectable,
                    'non_selectable_reason' => match (true) {
                        $stockStatus === 'negative' => '在庫がマイナスのため選択できません。',
                        $stockStatus === 'zero' && ! $hasCurrentAllocation => '在庫が0のため選択できません。',
                        ! $isActive => '無効なロットのため新規選択できません。',
                        ! $compatibility['selectable'] => '商品条件に適合しないため選択できません。',
                        default => null,
                    },
                    'recent_quantity' => $recentByLot->get($lot->id, '0.0000'),
                    'approval_request_id' => $currentAllocation?->approval_request_id,
                    'approval_status' => $currentAllocation?->approvalRequest?->status,
                ];
            })
            ->filter()
            ->sortBy([
                fn (array $a, array $b): int => strcmp($a['production_date'] ?? '9999-12-31', $b['production_date'] ?? '9999-12-31'),
                fn (array $a, array $b): int => strcmp($a['lot_code'], $b['lot_code']),
            ])
            ->values()
            ->all();

        return $this->ok([
            'shipment' => [
                'id' => $shipment->id,
                'document_number' => $shipment->document_number,
                'status' => $shipment->status,
            ],
            'shipment_line' => [
                'id' => $shipmentLine->id,
                'quantity' => $shipmentLine->quantity,
                'product_id' => $shipmentLine->product_id,
                'unit_id' => $shipmentLine->unit_id,
                'capacity_value' => $shipmentLine->product?->capacity_value,
                'capacity_unit_id' => $shipmentLine->product?->capacity_unit_id,
            ],
            'stock_location' => [
                'id' => $stockLocation->id,
                'name' => $stockLocation->name,
            ],
            'allocations' => $currentAllocations
                ->map(fn (ShipmentLotAllocation $allocation): array => $this->serializeAllocation($allocation))
                ->values()
                ->all(),
            'recent_allocation' => $recentAllocation,
            'candidates' => $candidates,
        ]);
    }

    public function saveLineLots(
        Request $request,
        ShipmentInstruction $shipmentInstruction,
        ShipmentInstructionLine $shipmentInstructionLine,
        CreateDraftShipmentFromInstructionService $draftService,
        SavePickingLotAllocationsService $saveService,
    ): JsonResponse {
        $this->abortIfLineDoesNotBelongToInstruction($shipmentInstruction, $shipmentInstructionLine);

        $validated = $request->validate([
            'allocations' => ['array'],
            'allocations.*.production_lot_id' => ['required', 'integer', 'exists:production_lots,id'],
            'allocations.*.quantity' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $shipment = $draftService->create($shipmentInstruction);
        $shipmentLine = $this->shipmentLineForInstructionLine($shipment, $shipmentInstructionLine);
        $stockLocation = $this->stockLocationForInstruction($shipmentInstruction);
        $reason = $validated['reason'] ?? 'ピッキング画面でのロット編集';
        $lines = collect($validated['allocations'] ?? [])
            ->map(fn (array $line): array => [
                'production_lot_id' => (int) $line['production_lot_id'],
                'quantity' => bcadd((string) $line['quantity'], '0', 4),
            ])
            ->filter(fn (array $line): bool => bccomp($line['quantity'], '0.0000', 4) > 0)
            ->values();

        $total = $lines->reduce(fn (string $carry, array $line): string => bcadd($carry, $line['quantity'], 4), '0.0000');
        if (bccomp($total, (string) $shipmentLine->quantity, 4) > 0) {
            abort(422, 'ロット割当本数が出荷本数を超えています。');
        }

        $hasConfirmed = ShipmentLotAllocation::query()
            ->where('shipment_line_id', $shipmentLine->id)
            ->where('status', 'confirmed')
            ->whereNull('cancelled_at')
            ->exists();

        if ($shipment->status === 'confirmed' && ! $hasConfirmed) {
            $this->saveConfirmedInitialLots($shipmentLine, $stockLocation, $lines, $reason);

            return $this->ok([
                'allocations' => $this->currentAllocations($shipmentLine)
                    ->map(fn (ShipmentLotAllocation $allocation): array => $this->serializeAllocation($allocation))
                    ->values()
                    ->all(),
            ]);
        }

        if ($shipment->status !== 'draft') {
            abort(422, '出荷確定済みのロット訂正は、履歴付き訂正画面で行う必要があります。');
        }

        if ($hasConfirmed) {
            abort(422, '出荷確定済みのロット割当はこの画面では変更できません。');
        }

        $allocations = $saveService->save($shipment, $shipmentInstructionLine, $stockLocation, $lines, $reason, $request->user());

        $shipmentLine->refresh()->load(['lotAllocations.productionLot', 'lotAllocations.stockLocation', 'lotAllocations.unit']);

        return $this->ok([
            'allocations' => $allocations
                ->map(fn (ShipmentLotAllocation $allocation): array => $this->serializeAllocation($allocation))
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePick(ShipmentPick $pick): array
    {
        $salesOrder = $pick->shipmentInstruction?->lines->first()?->salesOrder;

        return [
            'id' => $pick->id,
            'pick_number' => $pick->pick_number,
            'status' => $pick->status,
            'shipment_instruction_id' => $pick->shipment_instruction_id,
            'shipment_instruction_number' => $pick->shipmentInstruction?->instruction_number,
            'sales_order_number' => $salesOrder?->order_number,
            'customer_id' => $pick->shipmentInstruction?->customer_id,
            'customer_name' => $pick->shipmentInstruction?->customer?->name,
            'pick_date' => $pick->pick_date?->toDateString(),
            'stock_location_id' => $pick->stock_location_id,
            'stock_location_code' => $pick->stockLocation?->code,
            'stock_location_name' => $pick->stockLocation?->name,
            'shipment_header_id' => $pick->shipmentHeader?->id,
            'shipment_status' => $pick->shipmentHeader?->status,
            'cancelled_reason' => $pick->cancelled_reason,
            'cancelled_at' => $pick->cancelled_at?->toISOString(),
            'lines' => $pick->lines
                ->map(fn (ShipmentPickLine $line): array => [
                    'id' => $line->id,
                    'line_no' => $line->line_no,
                    'shipment_instruction_line_id' => $line->shipment_instruction_line_id,
                    'product_id' => $line->product_id,
                    'product_name' => $line->product?->name,
                    'quantity' => $line->quantity,
                    'unit_id' => $line->unit_id,
                    'unit_code' => $line->unit?->code,
                    'unit_name' => $line->unit?->name,
                    'note' => $line->note,
                    'lot_allocations' => $line->lotAllocations->map(fn (ShipmentLotAllocation $allocation): array => $this->serializeAllocation($allocation))->values()->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    private function abortIfLineDoesNotBelongToInstruction(ShipmentInstruction $instruction, ShipmentInstructionLine $line): void
    {
        if ($line->shipment_instruction_id !== $instruction->id) {
            abort(404);
        }
    }

    private function shipmentLineForInstructionLine(ShipmentHeader $shipment, ShipmentInstructionLine $instructionLine): ShipmentLine
    {
        $shipment->loadMissing('lines');

        $line = $shipment->lines
            ->first(fn (ShipmentLine $shipmentLine): bool => $shipmentLine->line_no === $instructionLine->line_no
                && $shipmentLine->product_id === $instructionLine->product_id
                && $shipmentLine->unit_id === $instructionLine->unit_id);

        if (! $line) {
            $line = $shipment->lines
                ->first(fn (ShipmentLine $shipmentLine): bool => $shipmentLine->product_id === $instructionLine->product_id
                    && $shipmentLine->unit_id === $instructionLine->unit_id
                    && bccomp((string) $shipmentLine->quantity, (string) $instructionLine->quantity, 4) === 0);
        }

        if (! $line) {
            abort(422, '出荷明細と出荷指示明細を対応付けできません。');
        }

        return $line;
    }

    private function stockLocationForInstruction(ShipmentInstruction $instruction): StockLocation
    {
        if ($instruction->stock_location_id) {
            return StockLocation::findOrFail($instruction->stock_location_id);
        }

        $location = StockLocation::query()
            ->where('is_default_shipping_location', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if (! $location) {
            abort(422, '出荷拠点が設定されていません。');
        }

        return $location;
    }

    private function currentAllocations(ShipmentLine $shipmentLine)
    {
        return ShipmentLotAllocation::query()
            ->with(['productionLot', 'stockLocation', 'unit', 'approvalRequest'])
            ->where('shipment_line_id', $shipmentLine->id)
            ->whereIn('status', ['allocated', 'confirmed'])
            ->whereNull('cancelled_at')
            ->orderBy('id')
            ->get();
    }

    private function currentAllocationsForInstructionLine(ShipmentHeader $shipment, ShipmentInstructionLine $instructionLine)
    {
        $lineIds = ShipmentLine::query()
            ->where('shipment_header_id', $shipment->id)
            ->where(function ($query) use ($instructionLine): void {
                $query->where('shipment_instruction_line_id', $instructionLine->id)
                    ->orWhere(function ($query) use ($instructionLine): void {
                        $query->whereNull('shipment_instruction_line_id')->where('line_no', $instructionLine->line_no)->where('product_id', $instructionLine->product_id);
                    });
            })->pluck('id');

        return ShipmentLotAllocation::query()
            ->with(['productionLot', 'stockLocation', 'unit', 'approvalRequest'])
            ->whereIn('shipment_line_id', $lineIds)
            ->whereIn('status', ['allocated', 'confirmed'])
            ->whereNull('cancelled_at')->orderBy('id')->get();
    }

    private function saveConfirmedInitialLots($shipmentLine, StockLocation $stockLocation, $lines, ?string $reason): void
    {
        $shipmentLine->loadMissing('shipmentHeader');
        $shipment = $shipmentLine->shipmentHeader;

        if ($shipment->invoiceLines()
            ->whereHas('invoiceHeader', fn ($query) => $query->where('status', 'confirmed'))
            ->exists()) {
            abort(422, '請求書発行済みのため、出荷確定後のロット割当はできません。');
        }

        $total = $lines->reduce(fn (string $carry, array $line): string => bcadd($carry, $line['quantity'], 4), '0.0000');
        if (bccomp($total, (string) $shipmentLine->quantity, 4) !== 0) {
            abort(422, '出荷確定済みの初回ロット割当は、出荷本数と同じ本数を割り当ててください。');
        }

        if (ShipmentLotAllocation::query()
            ->where('shipment_line_id', $shipmentLine->id)
            ->whereNull('cancelled_at')
            ->exists()) {
            abort(422, '既にロット割当があります。出荷確定後のロット訂正は履歴付き訂正画面で行う必要があります。');
        }

        DB::transaction(function () use ($shipmentLine, $shipment, $stockLocation, $lines, $reason): void {
            foreach ($lines as $line) {
                $lot = ProductionLot::findOrFail($line['production_lot_id']);

                ShipmentLotAllocation::create([
                    'status' => 'confirmed',
                    'shipment_header_id' => $shipment->id,
                    'shipment_line_id' => $shipmentLine->id,
                    'product_id' => $shipmentLine->product_id,
                    'production_lot_id' => $lot->id,
                    'stock_location_id' => $stockLocation->id,
                    'unit_id' => $shipmentLine->unit_id,
                    'quantity' => $line['quantity'],
                    'allocated_at' => now(),
                    'confirmed_at' => now(),
                    'reason' => $reason,
                ]);
            }

            $sourceMovements = StockMovement::query()
                ->where('source_shipment_line_id', $shipmentLine->id)
                ->where('movement_type', 'shipment')
                ->whereNull('cancelled_at')
                ->lockForUpdate()
                ->get();

            if ($sourceMovements->count() === 1 && $lines->count() === 1) {
                $lot = ProductionLot::findOrFail($lines->first()['production_lot_id']);
                $sourceMovements->first()->update([
                    'production_lot_id' => $lot->id,
                    'lot_code' => $lot->lot_code,
                    'reason' => trim((string) $sourceMovements->first()->reason."\n".($reason ?? '出荷確定後の初回ロット割当')),
                ]);

                return;
            }

            foreach ($sourceMovements as $movement) {
                $movement->update([
                    'cancelled_at' => now(),
                    'cancelled_reason' => $reason ?? '出荷確定後の初回ロット割当によりロット付き在庫移動へ置換',
                ]);
            }

            foreach ($lines as $line) {
                $lot = ProductionLot::findOrFail($line['production_lot_id']);
                $template = $sourceMovements->first();

                StockMovement::create([
                    'status' => 'confirmed',
                    'movement_type' => 'shipment',
                    'movement_date' => $template?->movement_date?->toDateString() ?? $shipment->document_date?->toDateString() ?? now()->toDateString(),
                    'stock_location_id' => $stockLocation->id,
                    'unit_id' => $shipmentLine->unit_id,
                    'quantity' => bcmul($line['quantity'], '-1', 4),
                    'source_type' => $template?->source_type ?? 'shipment',
                    'source_document_number' => $template?->source_document_number ?? $shipment->document_number,
                    'source_line_no' => $shipmentLine->line_no,
                    'source_shipment_header_id' => $shipment->id,
                    'source_shipment_line_id' => $shipmentLine->id,
                    'related_stock_movement_id' => $template?->id,
                    'production_lot_id' => $lot->id,
                    'lot_code' => $lot->lot_code,
                    'confirmed_at' => now(),
                    'reason' => $reason ?? '出荷確定後の初回ロット割当',
                ]);
            }
        });
    }

    /**
     * @return array{shipment_document_number: string|null, transaction_date: string|null, allocations: array<int, array<string, mixed>>}
     */
    private function recentAllocationForLine(
        ShipmentInstruction $instruction,
        ShipmentInstructionLine $instructionLine,
        int $currentShipmentId,
    ): array {
        $instructionLine->loadMissing('salesOrder');
        $referenceDate = $instructionLine->salesOrder?->order_date?->toDateString()
            ?? $instruction->instruction_date?->toDateString()
            ?? now()->toDateString();

        $recentLine = ShipmentLine::query()
            ->join('shipment_headers', 'shipment_headers.id', '=', 'shipment_lines.shipment_header_id')
            ->where('shipment_headers.id', '!=', $currentShipmentId)
            ->where('shipment_headers.status', '!=', 'cancelled')
            ->whereNull('shipment_headers.cancelled_at')
            ->whereDate('shipment_headers.document_date', '<=', $referenceDate)
            ->where('shipment_lines.product_id', $instructionLine->product_id)
            ->where('shipment_lines.unit_id', $instructionLine->unit_id)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('shipment_lot_allocations')
                    ->whereColumn('shipment_lot_allocations.shipment_line_id', 'shipment_lines.id')
                    ->whereIn('shipment_lot_allocations.status', ['allocated', 'confirmed'])
                    ->whereNull('shipment_lot_allocations.cancelled_at');
            })
            ->orderByDesc('shipment_headers.document_date')
            ->orderByDesc('shipment_headers.id')
            ->select([
                'shipment_lines.id',
                'shipment_headers.document_number',
                'shipment_headers.document_date',
            ])
            ->first();

        if (! $recentLine) {
            return [
                'shipment_document_number' => null,
                'transaction_date' => null,
                'allocations' => [],
            ];
        }

        return [
            'shipment_document_number' => $recentLine->document_number,
            'transaction_date' => $recentLine->document_date instanceof CarbonInterface
                ? $recentLine->document_date->toDateString()
                : (string) $recentLine->document_date,
            'allocations' => ShipmentLotAllocation::query()
                ->with('productionLot')
                ->where('shipment_line_id', $recentLine->id)
                ->whereIn('status', ['allocated', 'confirmed'])
                ->whereNull('cancelled_at')
                ->orderBy('id')
                ->get()
                ->map(fn (ShipmentLotAllocation $allocation): array => [
                    'production_lot_id' => $allocation->production_lot_id,
                    'lot_code' => $allocation->productionLot?->lot_code,
                    'display_name' => $allocation->productionLot?->display_name,
                    'quantity' => $allocation->quantity,
                ])
                ->values()
                ->all(),
        ];
    }

    private function serializeAllocation(ShipmentLotAllocation $allocation): array
    {
        return [
            'id' => $allocation->id,
            'status' => $allocation->status,
            'production_lot_id' => $allocation->production_lot_id,
            'lot_code' => $allocation->productionLot?->lot_code,
            'display_name' => $allocation->productionLot?->display_name,
            'quantity' => $allocation->quantity,
            'stock_location_id' => $allocation->stock_location_id,
            'stock_location_name' => $allocation->stockLocation?->name,
            'unit_id' => $allocation->unit_id,
            'unit_name' => $allocation->unit?->name,
            'standard_alcohol_percentage' => $allocation->standard_alcohol_percentage,
            'actual_alcohol_percentage' => $allocation->actual_alcohol_percentage,
            'allowed_alcohol_min' => $allocation->allowed_alcohol_min,
            'allowed_alcohol_max' => $allocation->allowed_alcohol_max,
            'alcohol_compliance_status' => $allocation->alcohol_compliance_status,
            'approval_request_id' => $allocation->approval_request_id,
            'approval_status' => $allocation->approvalRequest?->status,
        ];
    }
}
