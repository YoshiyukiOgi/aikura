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
            ->where(function (Builder $operationalPeriodQuery): void {
                $operationalPeriodQuery
                    ->where(function (Builder $legacyQuery): void {
                        $legacyQuery
                            ->whereNotNull('legacy_access_document_number')
                            ->whereNotNull('legacy_access_billing_year')
                            ->whereNotNull('legacy_access_billing_month')
                            ->whereRaw(
                                "make_date(legacy_access_billing_year, legacy_access_billing_month, 1) >= date_trunc('month', ?::date)::date",
                                [$this->operationalPeriod->startDate()],
                            );
                    })
                    ->orWhere(function (Builder $standardQuery): void {
                        $standardQuery
                            ->where(function (Builder $withoutAccessPeriod): void {
                                $withoutAccessPeriod
                                    ->whereNull('legacy_access_document_number')
                                    ->orWhereNull('legacy_access_billing_year')
                                    ->orWhereNull('legacy_access_billing_month');
                            })
                            ->whereDate('billing_target_date', '>=', $this->operationalPeriod->startDate());
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
            ->when($billingTargetFrom !== null || $billingTargetTo !== null, function (Builder $query) use ($billingTargetFrom, $billingTargetTo): void {
                $query->where(function (Builder $periodQuery) use ($billingTargetFrom, $billingTargetTo): void {
                    $periodQuery
                        ->where(function (Builder $legacyQuery) use ($billingTargetFrom, $billingTargetTo): void {
                            $legacyQuery
                                ->whereNotNull('legacy_access_document_number')
                                ->whereNotNull('legacy_access_billing_year')
                                ->whereNotNull('legacy_access_billing_month');

                            if ($billingTargetTo !== null) {
                                $legacyQuery->whereRaw(
                                    "make_date(legacy_access_billing_year, legacy_access_billing_month, 1) = date_trunc('month', ?::date)::date",
                                    [$billingTargetTo],
                                );
                            } elseif ($billingTargetFrom !== null) {
                                $legacyQuery->whereRaw(
                                    "make_date(legacy_access_billing_year, legacy_access_billing_month, 1) >= date_trunc('month', ?::date)::date",
                                    [$billingTargetFrom],
                                );
                            }
                        })
                        ->orWhere(function (Builder $standardQuery) use ($billingTargetFrom, $billingTargetTo): void {
                            $standardQuery->where(function (Builder $withoutAccessPeriod): void {
                                $withoutAccessPeriod
                                    ->whereNull('legacy_access_document_number')
                                    ->orWhereNull('legacy_access_billing_year')
                                    ->orWhereNull('legacy_access_billing_month');
                            });
                            if ($billingTargetFrom !== null) {
                                $standardQuery->whereDate('billing_target_date', '>=', $billingTargetFrom);
                            }
                            if ($billingTargetTo !== null) {
                                $standardQuery->whereDate('billing_target_date', '<=', $billingTargetTo);
                            }
                        });
                });
            })
            ->orderBy('billing_target_date')
            ->orderBy('document_number');
    }
}
