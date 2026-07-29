<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\CancelShipmentInstructionRequest;
use App\Http\Requests\Api\V1\StoreShipmentInstructionRequest;
use App\Models\ShipmentInstruction;
use App\Models\ShipmentInstructionLine;
use App\Services\Shipment\CancelShippingFlowService;
use App\Services\ShipmentInstruction\CancelShipmentInstructionService;
use App\Services\ShipmentInstruction\CreateShipmentInstructionData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionLineData;
use App\Services\ShipmentInstruction\CreateShipmentInstructionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ShipmentInstructionController extends ApiController
{
    public function index(): JsonResponse
    {
        $instructions = ShipmentInstruction::query()
            ->with(['customer', 'stockLocation', 'lines.product.capacityUnit', 'lines.unit', 'lines.salesOrder', 'lines.salesOrderLine'])
            ->where('status', '!=', 'cancelled')
            ->whereDoesntHave('picks.shipmentHeader', fn ($query) => $query->where('status', 'confirmed'))
            ->whereNotIn('id', fn ($query) => $query
                ->select('source_shipment_instruction_id')
                ->from('shipment_headers')
                ->where('status', 'confirmed')
                ->whereNotNull('source_shipment_instruction_id'))
            ->orderByDesc('id')
            ->get()
            ->map(fn (ShipmentInstruction $instruction): array => $this->serializeInstruction($instruction))
            ->values()
            ->all();

        return $this->ok([
            'shipment_instructions' => $instructions,
        ]);
    }

    public function show(ShipmentInstruction $shipmentInstruction): JsonResponse
    {
        return $this->ok([
            'shipment_instruction' => $this->serializeInstruction(
                $shipmentInstruction->load(['customer', 'stockLocation', 'lines.product.capacityUnit', 'lines.unit', 'lines.salesOrder', 'lines.salesOrderLine']),
            ),
        ]);
    }

    public function store(
        StoreShipmentInstructionRequest $request,
        CreateShipmentInstructionService $service,
    ): JsonResponse {
        $validated = $request->validated();

        $instruction = $service->create(new CreateShipmentInstructionData(
            instructionDate: $validated['instruction_date'],
            scheduledShipmentDate: $validated['scheduled_shipment_date'] ?? null,
            stockLocationId: isset($validated['stock_location_id']) ? (int) $validated['stock_location_id'] : null,
            note: $validated['note'] ?? null,
            reason: $validated['reason'] ?? null,
            lines: array_map(
                fn (array $line): CreateShipmentInstructionLineData => new CreateShipmentInstructionLineData(
                    salesOrderLineId: (int) $line['sales_order_line_id'],
                    quantity: $line['quantity'],
                    note: $line['note'] ?? null,
                ),
                $validated['lines'],
            ),
        ));

        return $this->created([
            'shipment_instruction' => $this->serializeInstruction($instruction),
        ]);
    }

    public function cancel(
        CancelShipmentInstructionRequest $request,
        ShipmentInstruction $shipmentInstruction,
        CancelShipmentInstructionService $service,
    ): JsonResponse {
        $cancelled = $service->cancel($shipmentInstruction, $request->validated('reason'));

        return $this->ok([
            'shipment_instruction' => $this->serializeInstruction($cancelled),
        ]);
    }

    public function cancelShippingFlow(
        CancelShipmentInstructionRequest $request,
        ShipmentInstruction $shipmentInstruction,
        CancelShippingFlowService $service,
    ): JsonResponse {
        $service->cancel($shipmentInstruction, $request->validated('reason'));

        return $this->ok([]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInstruction(ShipmentInstruction $instruction): array
    {
        $salesOrder = $instruction->lines->first()?->salesOrder;
        $lotAllocatedByLineId = $this->lotAllocatedQuantitiesByLineId($instruction);

        return [
            'id' => $instruction->id,
            'instruction_number' => $instruction->instruction_number,
            'status' => $instruction->status,
            'customer_id' => $instruction->customer_id,
            'customer_name' => $instruction->customer?->name,
            'sales_order_id' => $salesOrder?->id,
            'sales_order_number' => $salesOrder?->order_number,
            'instruction_date' => $instruction->instruction_date?->toDateString(),
            'scheduled_shipment_date' => $instruction->scheduled_shipment_date?->toDateString(),
            'stock_location_id' => $instruction->stock_location_id,
            'stock_location_code' => $instruction->stockLocation?->code,
            'stock_location_name' => $instruction->stockLocation?->name,
            'cancelled_reason' => $instruction->cancelled_reason,
            'cancelled_at' => $instruction->cancelled_at?->toISOString(),
            'lines' => $instruction->lines
                ->map(fn (ShipmentInstructionLine $line): array => [
                    'id' => $line->id,
                    'line_no' => $line->line_no,
                    'sales_order_id' => $line->sales_order_id,
                    'sales_order_line_id' => $line->sales_order_line_id,
                    'product_id' => $line->product_id,
                    'product_code' => $line->product?->product_code,
                    'product_name' => $line->product?->display_name ?: $line->product?->name,
                    'capacity_value' => $line->product?->capacity_value,
                    'capacity_unit_id' => $line->product?->capacity_unit_id,
                    'capacity_unit_code' => $line->product?->capacityUnit?->code,
                    'capacity_unit_name' => $line->product?->capacityUnit?->symbol ?: $line->product?->capacityUnit?->name,
                    'quantity' => $line->quantity,
                    'picked_quantity' => $line->picked_quantity,
                    'lot_allocated_quantity' => $lotAllocatedByLineId->get($line->id, '0.0000'),
                    'unit_id' => $line->unit_id,
                    'unit_code' => $line->unit?->code,
                    'unit_name' => $line->unit?->name,
                    'note' => $line->note,
                ])
                ->values()
                ->all(),
        ];
    }

    private function lotAllocatedQuantitiesByLineId(ShipmentInstruction $instruction)
    {
        $instructionLineIds = $instruction->lines->pluck('id')->all();

        return DB::table('shipment_lot_allocations')
            ->join('shipment_lines', 'shipment_lines.id', '=', 'shipment_lot_allocations.shipment_line_id')
            ->join('shipment_headers', 'shipment_headers.id', '=', 'shipment_lines.shipment_header_id')
            ->leftJoin('shipment_pick_lines', 'shipment_pick_lines.id', '=', 'shipment_lines.source_shipment_pick_line_id')
            ->leftJoin('shipment_instruction_lines as direct_instruction_lines', function ($join) {
                $join->on('direct_instruction_lines.shipment_instruction_id', '=', 'shipment_headers.source_shipment_instruction_id')
                    ->on('direct_instruction_lines.line_no', '=', 'shipment_lines.line_no');
            })
            ->where(function ($query) use ($instruction, $instructionLineIds): void {
                $query->where('shipment_headers.source_shipment_instruction_id', $instruction->id)
                    ->orWhereIn('shipment_pick_lines.shipment_instruction_line_id', $instructionLineIds);
            })
            ->where('shipment_headers.status', '!=', 'cancelled')
            ->whereIn('shipment_lot_allocations.status', ['allocated', 'confirmed'])
            ->whereNull('shipment_lot_allocations.cancelled_at')
            ->whereRaw('COALESCE(shipment_pick_lines.shipment_instruction_line_id, direct_instruction_lines.id) IS NOT NULL')
            ->groupByRaw('COALESCE(shipment_pick_lines.shipment_instruction_line_id, direct_instruction_lines.id)')
            ->selectRaw('COALESCE(shipment_pick_lines.shipment_instruction_line_id, direct_instruction_lines.id) as instruction_line_id, COALESCE(SUM(shipment_lot_allocations.quantity), 0) as quantity')
            ->pluck('quantity', 'instruction_line_id');
    }
}
