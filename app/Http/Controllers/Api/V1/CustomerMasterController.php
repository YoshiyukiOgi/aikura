<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreCustomerMasterRequest;
use App\Http\Requests\Api\V1\UpdateCustomerMasterRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Services\Masters\SaveCustomerMasterService;
use App\Support\SearchTextNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerMasterController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'in:active,inactive,all'],
            'transaction_category_id' => ['nullable', 'integer', 'exists:transaction_categories,id'],
            'settlement_receivable_category_id' => ['nullable', 'integer', 'exists:settlement_receivable_categories,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'sort' => ['nullable', 'in:customer_code,name,updated_at'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $query = Customer::query()
            ->with(['transactionCategory:id,name', 'settlementReceivableCategory:id,name', 'billingCycle:id,name']);
        $search = SearchTextNormalizer::normalize($validated['q'] ?? null);
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where('search_key_normalized', 'ilike', $like);
            });
        }
        match ($validated['active'] ?? 'active') {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            default => null,
        };
        $query
            ->when($validated['transaction_category_id'] ?? null, fn (Builder $query, int $id) => $query->where('transaction_category_id', $id))
            ->when($validated['settlement_receivable_category_id'] ?? null, fn (Builder $query, int $id) => $query->where('settlement_receivable_category_id', $id));

        $paginator = $query
            ->orderBy($validated['sort'] ?? 'customer_code', $validated['direction'] ?? 'asc')
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? 40);

        return $this->ok([
            'customers' => collect($paginator->items())->map(fn (Customer $customer): array => $this->summary($customer))->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Customer $customer): JsonResponse
    {
        $customer->load(['transactionCategory:id,name', 'settlementReceivableCategory:id,name', 'billingCycle:id,name'])
            ->loadCount(['salesOrders', 'shipments', 'invoices', 'payments', 'priceRules']);
        $duplicates = $this->duplicateCandidates($customer);
        $history = AuditLog::query()
            ->with('user:id,name')
            ->where('target_table', 'customers')
            ->where('target_id', (string) $customer->id)
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
            'customer' => [
                ...$this->summary($customer),
                'name_kana' => $customer->name_kana,
                'billing_name' => $customer->billing_name,
                'postal_code' => $customer->postal_code,
                'address1' => $customer->address1,
                'address2' => $customer->address2,
                'phone' => $customer->phone,
                'fax' => $customer->fax,
                'email' => $customer->email,
                'contact_name' => $customer->contact_name,
                'transaction_category_id' => $customer->transaction_category_id,
                'settlement_receivable_category_id' => $customer->settlement_receivable_category_id,
                'billing_cycle_id' => $customer->billing_cycle_id,
                'tax_rounding_method' => $customer->tax_rounding_method,
                'tax_calculation_unit' => $customer->tax_calculation_unit,
                'amount_rounding_method' => $customer->amount_rounding_method,
                'invoice_required' => $customer->invoice_required,
                'legacy_code' => $customer->legacy_code,
                'legacy_name' => $customer->legacy_name,
                'note' => $customer->note,
                'disabled_at' => $customer->disabled_at?->toIso8601String(),
                'usage_counts' => [
                    'sales_orders' => $customer->sales_orders_count,
                    'shipments' => $customer->shipments_count,
                    'invoices' => $customer->invoices_count,
                    'payments' => $customer->payments_count,
                    'price_rules' => $customer->price_rules_count,
                ],
                'duplicate_candidates' => $duplicates,
                'history' => $history,
            ],
        ]);
    }

    public function store(StoreCustomerMasterRequest $request, SaveCustomerMasterService $service): JsonResponse
    {
        return $this->created(['customer' => $this->summary($service->create($request->validated())->load(['transactionCategory', 'settlementReceivableCategory', 'billingCycle']))]);
    }

    public function update(UpdateCustomerMasterRequest $request, Customer $customer, SaveCustomerMasterService $service): JsonResponse
    {
        return $this->ok(['customer' => $this->summary($service->update($customer, $request->validated())->load(['transactionCategory', 'settlementReceivableCategory', 'billingCycle']))]);
    }

    private function summary(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'customer_code' => $customer->customer_code,
            'name' => $customer->name,
            'short_name' => $customer->short_name,
            'transaction_category_name' => $customer->transactionCategory?->name,
            'settlement_receivable_category_name' => $customer->settlementReceivableCategory?->name,
            'billing_cycle_name' => $customer->billingCycle?->name,
            'is_active' => $customer->is_active,
            'updated_at' => $customer->updated_at?->toIso8601String(),
        ];
    }

    private function duplicateCandidates(Customer $customer): array
    {
        $conditions = collect([
            'name' => $customer->name,
            'phone' => $customer->phone,
            'address1' => $customer->address1,
        ])->filter(fn (?string $value): bool => filled($value));
        if ($conditions->isEmpty()) {
            return [];
        }

        return Customer::query()
            ->where('id', '!=', $customer->id)
            ->where(function (Builder $query) use ($conditions): void {
                foreach ($conditions as $column => $value) {
                    $query->orWhere($column, $value);
                }
            })
            ->limit(10)
            ->get(['id', 'customer_code', 'name', 'phone', 'address1', 'is_active'])
            ->map(fn (Customer $candidate): array => [
                'id' => $candidate->id,
                'customer_code' => $candidate->customer_code,
                'name' => $candidate->name,
                'is_active' => $candidate->is_active,
                'matches' => $conditions->keys()->filter(fn (string $column): bool => $candidate->{$column} === $customer->{$column})->values()->all(),
            ])
            ->all();
    }
}
