<?php

namespace App\Services\Billing;

use App\Models\ShipmentHeader;
use Illuminate\Database\Eloquent\Builder;

class BillableShipmentQuery
{
    /**
     * @return Builder<ShipmentHeader>
     */
    public function query(?int $customerId = null, ?string $billingTargetFrom = null, ?string $billingTargetTo = null): Builder
    {
        return ShipmentHeader::query()
            ->with(['customer.billingCycle', 'lines'])
            ->where('status', 'confirmed')
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
