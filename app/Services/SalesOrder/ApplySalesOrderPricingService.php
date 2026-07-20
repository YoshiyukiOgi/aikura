<?php

namespace App\Services\SalesOrder;

use App\Exceptions\SalesOrder\SalesOrderException;
use App\Models\SalesOrder;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use App\Services\Pricing\ResolvePriceService;
use Illuminate\Support\Facades\DB;

class ApplySalesOrderPricingService
{
    public function __construct(
        private readonly ResolvePriceService $resolvePriceService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function apply(SalesOrder $salesOrder, ?string $reason = null): SalesOrder
    {
        return DB::transaction(function () use ($salesOrder, $reason): SalesOrder {
            $salesOrder = SalesOrder::query()
                ->with(['customer', 'lines.product', 'lines.unit'])
                ->lockForUpdate()
                ->findOrFail($salesOrder->id);

            if ($salesOrder->status !== 'received') {
                throw SalesOrderException::notPriceEditable($salesOrder->id, $salesOrder->status);
            }

            $pricedLineIds = [];

            foreach ($salesOrder->lines as $line) {
                $resolved = $this->resolvePriceService->resolve(
                    customer: $salesOrder->customer,
                    product: $line->product,
                    pricingDate: $salesOrder->order_date,
                    unitId: $line->unit_id,
                );

                $line->update([
                    'unit_price' => $resolved->unitPrice,
                    'price_list_id' => $resolved->priceListId,
                    'price_rule_id' => $resolved->priceRuleId,
                    'price_source' => $resolved->source,
                    'price_reason' => $resolved->reason,
                    'priced_at' => now(),
                ]);
                $pricedLineIds[] = $line->id;
            }

            $this->auditLogService->record(new AuditLogData(
                event: 'sales_order.priced',
                auditable: $salesOrder,
                afterValues: ['priced_line_ids' => $pricedLineIds],
                reason: $reason,
            ));

            return $salesOrder->refresh()->load(['customer', 'lines.product', 'lines.unit', 'lines.priceList', 'lines.priceRule']);
        });
    }
}
