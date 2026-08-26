<?php

namespace App\Services\Pricing;

use App\Exceptions\Pricing\PriceResolutionException;
use App\Models\Customer;
use App\Models\PriceRule;
use App\Models\Product;
use Illuminate\Support\Carbon;

class ResolvePriceService
{
    public function resolve(Customer $customer, Product $product, Carbon|string|null $pricingDate = null, ?int $unitId = null): ResolvedPrice
    {
        $date = $pricingDate instanceof Carbon
            ? $pricingDate->toDateString()
            : Carbon::parse($pricingDate ?? 'today')->toDateString();

        $rule = $this->baseQuery($product, $date, $unitId)
            ->where(function ($query) use ($customer): void {
                $query
                    ->where('customer_id', $customer->id)
                    ->orWhere(function ($query) use ($customer): void {
                        $query->whereNull('customer_id')
                            ->where('transaction_category_id', $customer->transaction_category_id);
                    })
                    ->orWhere(function ($query): void {
                        $query->whereNull('customer_id')
                            ->whereNull('transaction_category_id');
                    });
            })
            ->orderByRaw(
                'case when customer_id = ? then 0 when customer_id is null and transaction_category_id = ? then 1 else 2 end',
                [$customer->id, $customer->transaction_category_id],
            )
            ->orderBy('priority')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        if (! $rule) {
            throw PriceResolutionException::notFound($customer->id, $product->id, $date);
        }

        return $this->resolved($rule);
    }

    public function resolveTransactionCategoryPrice(Customer $customer, Product $product, Carbon|string|null $pricingDate = null, ?int $unitId = null): ResolvedPrice
    {
        $date = $pricingDate instanceof Carbon
            ? $pricingDate->toDateString()
            : Carbon::parse($pricingDate ?? 'today')->toDateString();

        $rule = $this->baseQuery($product, $date, $unitId)
            ->whereNull('customer_id')
            ->where(function ($query) use ($customer): void {
                $query
                    ->where('transaction_category_id', $customer->transaction_category_id)
                    ->orWhereNull('transaction_category_id');
            })
            ->orderByRaw('case when transaction_category_id = ? then 0 else 1 end', [$customer->transaction_category_id])
            ->orderBy('priority')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        if (! $rule) {
            throw PriceResolutionException::notFound($customer->id, $product->id, $date);
        }

        return $this->resolved($rule);
    }

    private function baseQuery(Product $product, string $date, ?int $unitId)
    {
        return PriceRule::query()
            ->with('priceList')
            ->where('product_id', $product->id)
            ->when($unitId !== null, function ($query) use ($unitId): void {
                $query->where('unit_id', $unitId);
            })
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            })
            ->whereHas('priceList', function ($query): void {
                $query->where('is_active', true);
            });
    }

    private function resolved(PriceRule $rule): ResolvedPrice
    {
        $previousRule = $this->previousRule($rule);
        $previousUnitPrice = $previousRule?->unit_price;

        return new ResolvedPrice(
            unitPrice: $rule->unit_price,
            priceListId: $rule->price_list_id,
            priceRuleId: $rule->id,
            unitId: $rule->unit_id,
            source: $this->source($rule),
            reason: $this->reason($rule),
            effectiveFrom: $rule->effective_from->toDateString(),
            previousUnitPrice: $previousUnitPrice,
            changeNotice: $this->changeNotice($rule, $previousUnitPrice),
            priceList: $rule->priceList,
            priceRule: $rule,
        );
    }

    private function previousRule(PriceRule $rule): ?PriceRule
    {
        return PriceRule::query()
            ->where('product_id', $rule->product_id)
            ->where('unit_id', $rule->unit_id)
            ->whereKeyNot($rule->id)
            ->whereDate('effective_from', '<', $rule->effective_from)
            ->when(
                $rule->customer_id !== null,
                fn ($query) => $query->where('customer_id', $rule->customer_id),
                fn ($query) => $query->whereNull('customer_id'),
            )
            ->when(
                $rule->customer_id === null && $rule->transaction_category_id !== null,
                fn ($query) => $query->where('transaction_category_id', $rule->transaction_category_id),
                fn ($query) => $query->whereNull('transaction_category_id'),
            )
            ->orderByDesc('effective_from')
            ->orderBy('priority')
            ->orderByDesc('id')
            ->first();
    }

    private function changeNotice(PriceRule $rule, ?string $previousUnitPrice): ?string
    {
        if ($previousUnitPrice === null || bccomp($previousUnitPrice, (string) $rule->unit_price, 4) === 0) {
            return null;
        }

        return $rule->effective_from->format('n月j日').'改定価格を適用（旧価格 '
            .number_format((float) $previousUnitPrice).'円 → 新価格 '
            .number_format((float) $rule->unit_price).'円）';
    }

    private function source(PriceRule $rule): string
    {
        if ($rule->customer_id !== null) {
            return 'customer';
        }

        if ($rule->transaction_category_id !== null) {
            return 'transaction_category';
        }

        return $rule->priceList->price_type;
    }

    private function reason(PriceRule $rule): string
    {
        return match ($this->source($rule)) {
            'customer' => '取引先個別価格',
            'transaction_category' => '取引区分価格',
            'common' => '共通価格表',
            'standard' => '商品標準価格',
            default => '価格ルール',
        };
    }
}
