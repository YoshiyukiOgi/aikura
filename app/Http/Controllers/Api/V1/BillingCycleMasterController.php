<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreBillingCycleMasterRequest;
use App\Http\Requests\Api\V1\UpdateBillingCycleMasterRequest;
use App\Models\AuditLog;
use App\Models\BillingCycle;
use App\Services\Masters\SaveBillingCycleMasterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingCycleMasterController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'active' => ['nullable', 'in:active,inactive,all'],
        ]);
        $query = BillingCycle::query()
            ->withCount(['customers', 'salesOrders', 'shipments', 'invoices'])
            ->orderByRaw("case billing_method when 'monthly_closing' then 1 when 'per_shipment' then 2 else 3 end")
            ->orderBy('closing_day')
            ->orderBy('payment_month_offset')
            ->orderBy('payment_day');
        if (($validated['active'] ?? 'all') !== 'all') {
            $query->where('is_active', ($validated['active'] ?? 'all') === 'active');
        }

        return $this->ok([
            'billing_cycles' => $query->get()->map(fn (BillingCycle $cycle): array => $this->summary($cycle))->all(),
        ]);
    }

    public function show(BillingCycle $billingCycle): JsonResponse
    {
        $billingCycle->loadCount(['customers', 'salesOrders', 'shipments', 'invoices']);
        $history = AuditLog::query()
            ->with('user:id,name')
            ->where('target_table', 'billing_cycles')
            ->where('target_id', (string) $billingCycle->id)
            ->latest('occurred_at')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (AuditLog $log): array => [
                'event' => $log->event,
                'occurred_at' => $log->occurred_at?->toIso8601String(),
                'user_name' => $log->user?->name,
                'reason' => $log->reason,
            ]);

        return $this->ok([
            'billing_cycle' => [
                ...$this->summary($billingCycle),
                'description' => $billingCycle->description,
                'history' => $history,
            ],
        ]);
    }

    public function store(StoreBillingCycleMasterRequest $request, SaveBillingCycleMasterService $service): JsonResponse
    {
        return $this->created(['billing_cycle' => $this->summary($service->create($request->validated())->loadCount(['customers', 'salesOrders', 'shipments', 'invoices']))]);
    }

    public function update(UpdateBillingCycleMasterRequest $request, BillingCycle $billingCycle, SaveBillingCycleMasterService $service): JsonResponse
    {
        return $this->ok(['billing_cycle' => $this->summary($service->update($billingCycle, $request->validated())->loadCount(['customers', 'salesOrders', 'shipments', 'invoices']))]);
    }

    private function summary(BillingCycle $cycle): array
    {
        $counts = [
            'customers' => (int) ($cycle->customers_count ?? 0),
            'sales_orders' => (int) ($cycle->sales_orders_count ?? 0),
            'shipments' => (int) ($cycle->shipments_count ?? 0),
            'invoices' => (int) ($cycle->invoices_count ?? 0),
        ];

        return [
            'id' => $cycle->id,
            'code' => $cycle->code,
            'name' => $cycle->name,
            'billing_method' => $cycle->billing_method,
            'closing_day' => $cycle->closing_day,
            'payment_month_offset' => $cycle->payment_month_offset,
            'payment_day' => $cycle->payment_day,
            'is_active' => $cycle->is_active,
            'usage_counts' => $counts,
            'is_calculation_locked' => array_sum($counts) > 0,
            'updated_at' => $cycle->updated_at?->toIso8601String(),
        ];
    }
}
