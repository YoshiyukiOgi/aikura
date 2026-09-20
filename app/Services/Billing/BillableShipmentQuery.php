<?php

namespace App\Services\Billing;

use App\Models\ShipmentHeader;
use App\Services\Operations\OperationalPeriod;
use Illuminate\Database\Eloquent\Builder;

class BillableShipmentQuery
{
    public function __construct(private readonly OperationalPeriod $operationalPeriod) {}

    /**
     * @return Builder<ShipmentHeader>
     */
    public function query(?int $customerId = null, ?string $billingTargetFrom = null, ?string $billingTargetTo = null): Builder
    {
        return ShipmentHeader::query()
            ->with(['customer.billingCycle', 'lines'])
            ->where('status', 'confirmed')
            ->whereDate('billing_target_date', '>=', $this->operationalPeriod->startDate())
            ->where(function (Builder $query): void {
                $query
                    ->where('confirmed_receivable_method', '!=', 'none')
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->whereNull('confirmed_receivable_method')
                            ->whereHas('settlementReceivableCategory', fn (Builder $category) => $category->where('receivable_method', '!=', 'none'));
                    });
            })
            ->whereDoesntHave('invoiceLines', function (Builder $query): void {
                $query->whereHas('invoiceHeader', function (Builder $query): void {
                    $query
                        ->where('status', '!=', 'cancelled')
                        ->where('document_type', '!=', 'credit_memo');
                });
            })
            ->when($customerId !== null, function (Builder $query) use ($customerId): void {
                $query->where('customer_id', $customerId);
            })
            ->when($billingTargetFrom !== null, function (Builder $query) use ($billingTargetFrom): void {
                $query->whereDate('billing_target_date', '>=', $billingTargetFrom);
            })
            ->when($billingTargetTo !== null, function (Builder $query) use ($billingTargetTo): void {
                $query->whereDate('billing_target_date', '<=', $billingTargetTo);
            })
            ->orderBy('billing_target_date')
            ->orderBy('document_number');
    }
}
