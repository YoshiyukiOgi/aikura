<?php

namespace App\Http\Controllers\Retail;

use App\Http\Controllers\Controller;
use App\Models\Retail\RetailCompany;
use App\Models\Retail\RetailCustomer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RetailCustomerController extends Controller
{
    public function index(Request $request): View
    {
        $selectedCompany = $this->selectedCompany($request);
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
            'edit' => ['nullable', 'integer', 'min:1'],
        ]);

        $customers = RetailCustomer::query()
            ->with('company:id,name')
            ->availableToCompany($selectedCompany->id)
            ->when(($validated['q'] ?? '') !== '', function (Builder $query) use ($validated): void {
                $search = '%'.trim((string) $validated['q']).'%';
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('customer_code', 'ilike', $search)
                        ->orWhere('name', 'ilike', $search)
                        ->orWhere('name_kana', 'ilike', $search)
                        ->orWhere('billing_name', 'ilike', $search)
                        ->orWhere('phone', 'ilike', $search)
                        ->orWhere('address1', 'ilike', $search);
                });
            })
            ->when(($validated['active'] ?? 'active') === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when(($validated['active'] ?? 'active') === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->orderBy('customer_code')
            ->paginate(30)
            ->withQueryString();

        $editingCustomer = null;
        if (isset($validated['edit'])) {
            $editingCustomer = RetailCustomer::query()
                ->availableToCompany($selectedCompany->id)
                ->find($validated['edit']);
        }

        return view('retail.customers.index', [
            'customers' => $customers,
            'editingCustomer' => $editingCustomer,
            'companies' => RetailCompany::query()->where('is_active', true)->orderBy('id')->get(['id', 'name']),
            'selectedCompanyId' => $selectedCompany->id,
            'filters' => [
                'q' => $validated['q'] ?? '',
                'active' => $validated['active'] ?? 'active',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCustomer($request)->validate();

        RetailCustomer::query()->create($validated);

        return redirect()
            ->route('retail.customers.index')
            ->with('status', '小売顧客を登録しました。');
    }

    public function update(Request $request, RetailCustomer $retailCustomer): RedirectResponse
    {
        $validated = $this->validateCustomer($request, $retailCustomer)->validate();

        $retailCustomer->update($validated);

        return redirect()
            ->route('retail.customers.index', ['edit' => $retailCustomer->id])
            ->with('status', '小売顧客を更新しました。');
    }

    private function validateCustomer(Request $request, ?RetailCustomer $customer = null): \Illuminate\Validation\Validator
    {
        $selectedCompany = $this->selectedCompany($request);

        return Validator::make($request->all(), [
            'retail_company_id' => [
                'nullable',
                'integer',
                Rule::exists('retail.retail_companies', 'id')->where('is_active', true),
            ],
            'customer_code' => [
                'required',
                'string',
                'max:80',
                Rule::unique('retail.retail_customers', 'customer_code')->ignore($customer?->id),
            ],
            'name' => ['required', 'string', 'max:160'],
            'name_kana' => ['nullable', 'string', 'max:160'],
            'billing_name' => ['nullable', 'string', 'max:160'],
            'billing_method' => ['nullable', Rule::in(['monthly', 'per_sale', 'none'])],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'address1' => ['nullable', 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'fax' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'closing_day' => ['nullable', 'integer', 'between:1,31'],
            'payment_month_offset' => ['required', 'integer', 'between:0,3'],
            'payment_day' => ['nullable', 'integer', 'between:1,31'],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'invoice_required' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
        ])->after(function ($validator) use ($request): void {
            $invoiceRequired = in_array($request->input('billing_method'), ['monthly', 'per_sale'], true)
                || $request->boolean('invoice_required');
            if ($invoiceRequired
                && $request->input('billing_method') !== 'per_sale'
                && $request->input('billing_method') !== 'none'
                && blank($request->input('closing_day'))) {
                $validator->errors()->add('closing_day', '請求対象の顧客は締日を入力してください。');
            }
        })->setData([
            ...$request->all(),
            'retail_company_id' => $request->exists('retail_company_id')
                ? $request->input('retail_company_id')
                : $selectedCompany->id,
            'billing_method' => $request->input('billing_method') ?: null,
            'invoice_required' => match ($request->input('billing_method')) {
                'monthly', 'per_sale' => true,
                'none' => false,
                default => $request->boolean('invoice_required'),
            },
            'is_active' => $request->boolean('is_active'),
        ]);
    }

    private function selectedCompany(Request $request): RetailCompany
    {
        $companyKey = $request->session()->get('retail.company');
        $company = RetailCompany::activeByKey(is_string($companyKey) ? $companyKey : null)
            ?? RetailCompany::query()->where('is_active', true)->orderBy('id')->firstOrFail();

        $request->session()->put('retail.company', $company->company_key);

        return $company;
    }
}
