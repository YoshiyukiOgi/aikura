<?php

namespace App\Services\SalesOrder;

use App\Exceptions\SalesOrder\SalesOrderException;
use App\Models\PriceList;
use App\Models\PriceRule;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

class OverrideSalesOrderLinePriceService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    public function override(SalesOrder $salesOrder, SalesOrderLine $line, string $unitPrice, string $reason, bool $saveAsCustomerPrice = false): SalesOrder
    {
        if (bccomp($unitPrice, '0', 4) <= 0) {
            throw SalesOrderException::invalidUnitPrice($unitPrice);
        }

        return DB::transaction(function () use ($salesOrder, $line, $unitPrice, $reason, $saveAsCustomerPrice): SalesOrder {
            $salesOrder = SalesOrder::query()->lockForUpdate()->findOrFail($salesOrder->id);

            if ($salesOrder->status !== 'received') {
                throw SalesOrderException::notPriceEditable($salesOrder->id, $salesOrder->status);
            }

            $line = SalesOrderLine::query()->lockForUpdate()->findOrFail($line->id);
            if ($line->sales_order_id !== $salesOrder->id) {
                throw SalesOrderException::lineDoesNotBelong($line->id, $salesOrder->id);
            }

            $priceRule = null;
            if ($saveAsCustomerPrice) {
                $priceRule = $this->upsertCustomerPriceRule($salesOrder, $line, $unitPrice, $reason);
            }

            $before = ['unit_price' => $line->unit_price, 'price_source' => $line->price_source];
            $line->update([
                'unit_price' => $unitPrice,
                'price_list_id' => $priceRule?->price_list_id,
                'price_rule_id' => $priceRule?->id,
                'price_source' => $priceRule ? 'customer' : 'manual',
                'price_reason' => $priceRule ? '取引先個別価格' : $reason,
                'priced_at' => now(),
            ]);

            $this->auditLogService->record(new AuditLogData(
                event: 'sales_order_line.price_overridden',
                auditable: $line,
                beforeValues: $before,
                afterValues: ['unit_price' => $line->unit_price, 'price_source' => $line->price_source],
                reason: $reason,
            ));

            return $salesOrder->refresh()->load(['customer', 'lines.product', 'lines.unit', 'lines.priceList', 'lines.priceRule']);
        });
    }

    private function upsertCustomerPriceRule(SalesOrder $salesOrder, SalesOrderLine $line, string $unitPrice, string $reason): PriceRule
    {
        $priceList = PriceList::query()
            ->where('code', 'customer_price')
            ->orWhere('code', 'customer')
            ->orderByRaw("case when code = 'customer_price' then 0 else 1 end")
            ->firstOrFail();

        $effectiveFrom = $salesOrder->order_date?->toDateString() ?? now()->toDateString();

        $existing = PriceRule::query()
            ->where('product_id', $line->product_id)
            ->where('unit_id', $line->unit_id)
            ->where('customer_id', $salesOrder->customer_id)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $effectiveFrom)
            ->where(function ($query) use ($effectiveFrom): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $effectiveFrom);
            })
            ->orderBy('priority')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            $existing->update([
                'price_list_id' => $priceList->id,
                'unit_price' => $unitPrice,
                'currency' => 'JPY',
                'priority' => min((int) $existing->priority, 100),
                'effective_to' => null,
                'rounding_method' => $existing->rounding_method ?: 'round',
                'reason' => $reason,
                'is_active' => true,
            ]);

            return $existing->refresh();
        }

        return PriceRule::create([
            'price_list_id' => $priceList->id,
            'product_id' => $line->product_id,
            'customer_id' => $salesOrder->customer_id,
            'transaction_category_id' => null,
            'unit_id' => $line->unit_id,
            'unit_price' => $unitPrice,
            'currency' => 'JPY',
            'priority' => 100,
            'effective_from' => $effectiveFrom,
            'effective_to' => null,
            'rounding_method' => 'round',
            'reason' => $reason,
            'is_active' => true,
        ]);
    }
}
