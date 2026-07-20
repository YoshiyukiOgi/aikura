<?php

namespace App\Services\ShipmentInstruction;

use App\Exceptions\Shipment\ShipmentInstructionException;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\ShipmentInstruction;
use App\Models\StockLocation;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use App\Services\NumberSequence\NumberSequenceService;
use Illuminate\Support\Facades\DB;

class CreateShipmentInstructionService
{
    public function __construct(
        private readonly NumberSequenceService $numberSequenceService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function create(CreateShipmentInstructionData $data): ShipmentInstruction
    {
        if ($data->lines === []) {
            throw ShipmentInstructionException::emptyLines();
        }

        return DB::transaction(function () use ($data): ShipmentInstruction {
            $lines = $this->lockAndValidateLines($data->lines);
            $customerId = $lines[0]->salesOrder->customer_id;
            $stockLocationId = $this->resolveStockLocationId($data->stockLocationId);

            $instructionNumber = $this->numberSequenceService
                ->next('shipment_instruction')
                ->formatted;

            $instruction = ShipmentInstruction::create([
                'instruction_number' => $instructionNumber,
                'status' => 'instructed',
                'customer_id' => $customerId,
                'instruction_date' => $data->instructionDate,
                'scheduled_shipment_date' => $data->scheduledShipmentDate,
                'stock_location_id' => $stockLocationId,
                'note' => $data->note,
            ]);

            foreach (array_values($data->lines) as $index => $lineData) {
                $salesOrderLine = $lines[$index];
                $quantity = bcadd($lineData->quantity, '0', 4);

                $instruction->lines()->create([
                    'line_no' => $index + 1,
                    'sales_order_id' => $salesOrderLine->sales_order_id,
                    'sales_order_line_id' => $salesOrderLine->id,
                    'product_id' => $salesOrderLine->product_id,
                    'quantity' => $quantity,
                    'unit_id' => $salesOrderLine->unit_id,
                    'note' => $lineData->note,
                ]);

                $salesOrderLine->update([
                    'remaining_quantity' => bcsub((string) $salesOrderLine->remaining_quantity, $quantity, 4),
                ]);
            }

            $this->refreshSalesOrderStatuses($lines);

            $this->auditLogService->record(new AuditLogData(
                event: 'shipment_instruction.created',
                auditable: $instruction,
                afterValues: [
                    'instruction_number' => $instruction->instruction_number,
                    'status' => $instruction->status,
                    'customer_id' => $instruction->customer_id,
                    'line_count' => count($data->lines),
                ],
                reason: $data->reason,
            ));

            return $instruction->load(['customer', 'stockLocation', 'lines.salesOrderLine', 'lines.product', 'lines.unit']);
        });
    }

    /**
     * @param array<int, CreateShipmentInstructionLineData> $lineDataList
     * @return array<int, SalesOrderLine>
     */
    private function lockAndValidateLines(array $lineDataList): array
    {
        $lines = [];
        $customerId = null;
        $salesOrderId = null;
        $settlementReceivableCategoryId = null;
        $seenSalesOrderLineIds = [];

        foreach ($lineDataList as $lineData) {
            $quantity = bcadd($lineData->quantity, '0', 4);

            if (bccomp($quantity, '0.0000', 4) <= 0) {
                throw ShipmentInstructionException::invalidQuantity($quantity);
            }

            $salesOrderLine = SalesOrderLine::query()
                ->with('salesOrder')
                ->lockForUpdate()
                ->findOrFail($lineData->salesOrderLineId);

            if (isset($seenSalesOrderLineIds[$salesOrderLine->id])) {
                throw ShipmentInstructionException::duplicateSalesOrderLine($salesOrderLine->id);
            }

            $seenSalesOrderLineIds[$salesOrderLine->id] = true;

            if ($salesOrderLine->salesOrder->status === 'cancelled') {
                throw ShipmentInstructionException::cancelledSalesOrder($salesOrderLine->sales_order_id);
            }

            if ($customerId === null) {
                $customerId = $salesOrderLine->salesOrder->customer_id;
            } elseif ($customerId !== $salesOrderLine->salesOrder->customer_id) {
                throw ShipmentInstructionException::customerMismatch($customerId, $salesOrderLine->salesOrder->customer_id);
            }

            if ($salesOrderId === null) {
                $salesOrderId = $salesOrderLine->sales_order_id;
            } elseif ($salesOrderId !== $salesOrderLine->sales_order_id) {
                throw ShipmentInstructionException::multipleSalesOrders();
            }

            if ($settlementReceivableCategoryId === null) {
                $settlementReceivableCategoryId = $salesOrderLine->salesOrder->settlement_receivable_category_id;
            } elseif ($settlementReceivableCategoryId !== $salesOrderLine->salesOrder->settlement_receivable_category_id) {
                throw ShipmentInstructionException::settlementReceivableCategoryMismatch(
                    $settlementReceivableCategoryId,
                    $salesOrderLine->salesOrder->settlement_receivable_category_id,
                );
            }

            if (bccomp($quantity, (string) $salesOrderLine->remaining_quantity, 4) > 0) {
                throw ShipmentInstructionException::exceedsRemainingQuantity(
                    $salesOrderLine->id,
                    (string) $salesOrderLine->remaining_quantity,
                    $quantity,
                );
            }

            $lines[] = $salesOrderLine;
        }

        return $lines;
    }

    private function resolveStockLocationId(?int $stockLocationId): ?int
    {
        if ($stockLocationId !== null) {
            return StockLocation::query()->findOrFail($stockLocationId)->id;
        }

        return StockLocation::query()
            ->where('is_default_shipping_location', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->value('id');
    }

    /**
     * @param array<int, SalesOrderLine> $lines
     */
    private function refreshSalesOrderStatuses(array $lines): void
    {
        foreach (collect($lines)->pluck('sales_order_id')->unique() as $salesOrderId) {
            $salesOrder = SalesOrder::query()
                ->with('lines')
                ->lockForUpdate()
                ->findOrFail($salesOrderId);

            $hasRemaining = $salesOrder->lines->contains(
                fn (SalesOrderLine $line): bool => bccomp((string) $line->remaining_quantity, '0.0000', 4) > 0,
            );
            $hasInstructed = $salesOrder->lines->contains(
                fn (SalesOrderLine $line): bool => bccomp((string) $line->remaining_quantity, (string) $line->quantity, 4) < 0,
            );

            $salesOrder->update([
                'status' => $hasRemaining
                    ? ($hasInstructed ? 'partially_instructed' : 'received')
                    : 'instructed',
                'shipment_returned_at' => null,
                'shipment_returned_reason' => null,
            ]);
        }
    }
}
