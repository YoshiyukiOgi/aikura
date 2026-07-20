<?php

namespace App\Services\SalesOrder;

use App\Exceptions\SalesOrder\SalesOrderException;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SettlementReceivableCategory;
use App\Models\Unit;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use App\Services\NumberSequence\NumberSequenceService;
use App\Services\Pricing\ResolvePriceService;
use Illuminate\Support\Facades\DB;

class CreateSalesOrderService
{
    public function __construct(
        private readonly NumberSequenceService $numberSequenceService,
        private readonly AuditLogService $auditLogService,
        private readonly ResolvePriceService $resolvePriceService,
    ) {
    }

    public function create(CreateSalesOrderData $data): SalesOrder
    {
        if ($data->lines === []) {
            throw SalesOrderException::emptyLines();
        }

        return DB::transaction(function () use ($data): SalesOrder {
            $customer = Customer::query()->findOrFail($data->customerId);

            if (! $customer->is_active) {
                throw SalesOrderException::inactiveCustomer($customer->id);
            }

            $settlementReceivableCategoryId = $data->settlementReceivableCategoryId
                ?? $customer->settlement_receivable_category_id;

            $settlementReceivableCategory = SettlementReceivableCategory::query()
                ->findOrFail($settlementReceivableCategoryId);

            $orderNumber = $this->numberSequenceService
                ->next('sales_order')
                ->formatted;

            $salesOrder = SalesOrder::create([
                'order_number' => $orderNumber,
                'status' => 'received',
                'awaiting_shipment_instruction' => $data->awaitingShipmentInstruction,
                'customer_id' => $customer->id,
                'transaction_category_id' => $customer->transaction_category_id,
                'settlement_receivable_category_id' => $settlementReceivableCategory->id,
                'billing_cycle_id' => $customer->billing_cycle_id,
                'order_date' => $data->orderDate,
                'requested_shipment_date' => $data->requestedShipmentDate,
                'requested_delivery_date' => $data->requestedDeliveryDate,
                'billing_target_date' => $data->billingTargetDate ?? $data->orderDate,
                'customer_order_number' => $data->customerOrderNumber,
                'source_type' => $data->sourceType,
                'source_reference' => $data->sourceReference,
                'note' => $data->note,
                'work_note' => $data->workNote,
            ]);

            foreach (array_values($data->lines) as $index => $lineData) {
                $this->validateLine($lineData);

                $line = $salesOrder->lines()->create([
                    'line_no' => $index + 1,
                    'product_id' => $lineData->productId,
                    'quantity' => $lineData->quantity,
                    'unit_id' => $lineData->unitId,
                    'remaining_quantity' => $lineData->quantity,
                    'note' => $lineData->note,
                ]);

                if ($data->applyPricing) {
                    $resolved = $this->resolvePriceService->resolve(
                        customer: $customer,
                        product: Product::query()->findOrFail($lineData->productId),
                        pricingDate: $data->orderDate,
                        unitId: $lineData->unitId,
                    );

                    $line->update([
                        'unit_price' => $resolved->unitPrice,
                        'price_list_id' => $resolved->priceListId,
                        'price_rule_id' => $resolved->priceRuleId,
                        'price_source' => $resolved->source,
                        'price_reason' => $resolved->reason,
                        'priced_at' => now(),
                    ]);
                }
            }

            $this->auditLogService->record(new AuditLogData(
                event: 'sales_order.created',
                auditable: $salesOrder,
                afterValues: [
                    'order_number' => $salesOrder->order_number,
                    'status' => $salesOrder->status,
                    'customer_id' => $salesOrder->customer_id,
                    'line_count' => count($data->lines),
                ],
                reason: $data->reason,
            ));

            return $salesOrder->load(['customer', 'lines.product', 'lines.unit']);
        });
    }

    private function validateLine(CreateSalesOrderLineData $lineData): void
    {
        if (bccomp($lineData->quantity, '0', 4) <= 0) {
            throw SalesOrderException::invalidQuantity($lineData->quantity);
        }

        $product = Product::query()->findOrFail($lineData->productId);

        if (! $product->is_active || ! $product->is_sales_available) {
            throw SalesOrderException::inactiveProduct($product->id);
        }

        $unit = Unit::query()->findOrFail($lineData->unitId);

        if (! $unit->is_active) {
            throw SalesOrderException::inactiveUnit($unit->id);
        }
    }
}
