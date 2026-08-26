<?php

namespace App\Services\SalesOrder;

use App\Exceptions\SalesOrder\SalesOrderException;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\Unit;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use App\Services\Pricing\ResolvePriceService;
use Illuminate\Support\Facades\DB;

class UpdateSalesOrderService
{
    public function __construct(
        private readonly ResolvePriceService $resolvePriceService,
        private readonly AuditLogService $auditLogService,
    ) {}

    /** @param array<int, array<string, mixed>> $lines */
    public function update(SalesOrder $salesOrder, array $attributes, array $lines, ?string $reason = null, bool $allowRetailManaged = false): SalesOrder
    {
        return DB::transaction(function () use ($salesOrder, $attributes, $lines, $reason, $allowRetailManaged): SalesOrder {
            $salesOrder = SalesOrder::query()->with('customer')->lockForUpdate()->findOrFail($salesOrder->id);
            if ($salesOrder->isRetailManaged() && ! $allowRetailManaged) {
                throw SalesOrderException::retailManagedOrderCannotBeChanged($salesOrder->id);
            }

            if (! in_array($salesOrder->status, ['received', 'partially_instructed'], true)) {
                throw SalesOrderException::notEditable($salesOrder->id, $salesOrder->status);
            }

            $existingLines = SalesOrderLine::query()->where('sales_order_id', $salesOrder->id)->lockForUpdate()->get()->keyBy('id');
            $before = ['requested_shipment_date' => $salesOrder->requested_shipment_date?->toDateString(), 'requested_delivery_date' => $salesOrder->requested_delivery_date?->toDateString(), 'customer_order_number' => $salesOrder->customer_order_number, 'note' => $salesOrder->note, 'work_note' => $salesOrder->work_note, 'line_count' => $existingLines->count()];
            $keptIds = [];

            foreach ($existingLines as $existingLine) {
                $existingLine->update(['line_no' => 100000 + $existingLine->id]);
            }

            foreach (array_values($lines) as $index => $lineData) {
                $product = $this->validProduct((int) $lineData['product_id']);
                $this->validUnit((int) $lineData['unit_id']);
                $quantity = (string) $lineData['quantity'];
                if (bccomp($quantity, '0', 4) <= 0) {
                    throw SalesOrderException::invalidQuantity($quantity);
                }

                $lineId = isset($lineData['id']) ? (int) $lineData['id'] : null;
                $line = $lineId ? $existingLines->get($lineId) : null;
                if ($lineId && ! $line) {
                    throw SalesOrderException::lineDoesNotBelong($lineId, $salesOrder->id);
                }

                $instructedQuantity = $line
                    ? bcsub((string) $line->quantity, (string) $line->remaining_quantity, 4)
                    : '0.0000';
                $productOrUnitChanged = $line && ($line->product_id !== $product->id || $line->unit_id !== (int) $lineData['unit_id']);

                if ($line && bccomp($instructedQuantity, '0.0000', 4) > 0) {
                    if ($productOrUnitChanged) {
                        throw SalesOrderException::instructedLineCannotChange($line->id);
                    }

                    if (bccomp($quantity, $instructedQuantity, 4) < 0) {
                        throw SalesOrderException::quantityBelowInstructed($line->id, $instructedQuantity);
                    }
                }

                $values = [
                    'line_no' => $index + 1,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_id' => (int) $lineData['unit_id'],
                    'remaining_quantity' => bcsub($quantity, $instructedQuantity, 4),
                    'note' => $lineData['note'] ?? null,
                ];

                if (! $line || $productOrUnitChanged || $line->unit_price === null) {
                    $resolved = $this->resolvePriceService->resolve(customer: $salesOrder->customer, product: $product, pricingDate: $salesOrder->order_date, unitId: (int) $lineData['unit_id']);
                    $values += $resolved->salesOrderLineAttributes();
                }

                if ($line) {
                    $line->update($values);
                    $keptIds[] = $line->id;
                } else {
                    $keptIds[] = $salesOrder->lines()->create($values)->id;
                }
            }

            $deletedLines = $existingLines->except($keptIds);
            foreach ($deletedLines as $deletedLine) {
                $instructedQuantity = bcsub((string) $deletedLine->quantity, (string) $deletedLine->remaining_quantity, 4);
                if (bccomp($instructedQuantity, '0.0000', 4) > 0) {
                    throw SalesOrderException::instructedLineCannotDelete($deletedLine->id);
                }
            }
            SalesOrderLine::query()->where('sales_order_id', $salesOrder->id)->whereNotIn('id', $keptIds)->delete();
            $salesOrder->update($attributes);
            $this->auditLogService->record(new AuditLogData(event: 'sales_order.updated', auditable: $salesOrder, beforeValues: $before, afterValues: $attributes + ['line_count' => count($lines)], reason: $reason));

            return $salesOrder->refresh()->load(['customer', 'lines.product', 'lines.unit']);
        });
    }

    private function validProduct(int $productId): Product
    {
        $product = Product::query()->findOrFail($productId);
        if (! $product->is_active || ! $product->is_sales_available) {
            throw SalesOrderException::inactiveProduct($product->id);
        }

        return $product;
    }

    private function validUnit(int $unitId): void
    {
        $unit = Unit::query()->findOrFail($unitId);
        if (! $unit->is_active) {
            throw SalesOrderException::inactiveUnit($unit->id);
        }
    }
}
