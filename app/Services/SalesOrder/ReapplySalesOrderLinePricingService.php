<?php

namespace App\Services\SalesOrder;

use App\Exceptions\SalesOrder\SalesOrderException;
use App\Models\PriceRule;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use App\Services\Pricing\ResolvePriceService;
use Illuminate\Support\Facades\DB;

class ReapplySalesOrderLinePricingService
{
    public function __construct(
        private readonly ResolvePriceService $resolvePriceService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function reapply(SalesOrder $salesOrder, SalesOrderLine $line, ?string $reason = null): SalesOrder
    {
        return DB::transaction(function () use ($salesOrder, $line, $reason): SalesOrder {
            $salesOrder = SalesOrder::query()->with('customer')->lockForUpdate()->findOrFail($salesOrder->id);
            if ($salesOrder->status !== 'received') {
                throw SalesOrderException::notPriceEditable($salesOrder->id, $salesOrder->status);
            }

            $line = SalesOrderLine::query()->with(['product', 'unit'])->lockForUpdate()->findOrFail($line->id);
            if ($line->sales_order_id !== $salesOrder->id) {
                throw SalesOrderException::lineDoesNotBelong($line->id, $salesOrder->id);
            }

            $resolved = $this->resolvePriceService->resolveTransactionCategoryPrice(
                customer: $salesOrder->customer,
                product: $line->product,
                pricingDate: $salesOrder->order_date,
                unitId: $line->unit_id,
            );
            $before = ['unit_price' => $line->unit_price, 'price_source' => $line->price_source];

            $this->disableCustomerSpecificPriceRules($salesOrder, $line, $reason);

            $line->update([
                'unit_price' => $resolved->unitPrice,
                'price_list_id' => $resolved->priceListId,
                'price_rule_id' => $resolved->priceRuleId,
                'price_source' => $resolved->source,
                'price_reason' => $resolved->reason,
                'priced_at' => now(),
            ]);

            $this->auditLogService->record(new AuditLogData(
                event: 'sales_order_line.price_reapplied',
                auditable: $line,
                beforeValues: $before,
                afterValues: ['unit_price' => $line->unit_price, 'price_source' => $line->price_source],
                reason: $reason,
            ));

            return $salesOrder->refresh()->load(['customer', 'lines.product', 'lines.unit', 'lines.priceList', 'lines.priceRule']);
        });
    }

    private function disableCustomerSpecificPriceRules(SalesOrder $salesOrder, SalesOrderLine $line, ?string $reason): void
    {
        $orderDate = $salesOrder->order_date?->toDateString() ?? now()->toDateString();
        $previousDate = $salesOrder->order_date?->copy()->subDay()->toDateString() ?? now()->subDay()->toDateString();

        PriceRule::query()
            ->where('customer_id', $salesOrder->customer_id)
            ->where('product_id', $line->product_id)
            ->where('unit_id', $line->unit_id)
            ->where('is_active', true)
            ->where(function ($query) use ($orderDate): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $orderDate);
            })
            ->chunkById(50, function ($rules) use ($orderDate, $previousDate, $reason): void {
                foreach ($rules as $rule) {
                    $before = $rule->only(['unit_price', 'effective_from', 'effective_to', 'is_active']);

                    if ($rule->effective_from?->toDateString() >= $orderDate) {
                        $rule->update([
                            'is_active' => false,
                            'reason' => $reason ?: '既定価格へ戻すため取引先個別価格を無効化',
                        ]);
                    } else {
                        $rule->update([
                            'effective_to' => $previousDate,
                            'reason' => $reason ?: '既定価格へ戻すため取引先個別価格を終了',
                        ]);
                    }

                    $this->auditLogService->record(new AuditLogData(
                        event: 'price_rule.customer_specific_disabled_by_reprice',
                        auditable: $rule,
                        beforeValues: $before,
                        afterValues: $rule->only(['unit_price', 'effective_from', 'effective_to', 'is_active']),
                        reason: $reason,
                    ));
                }
            });
    }
}
