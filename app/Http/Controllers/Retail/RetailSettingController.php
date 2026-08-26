<?php

namespace App\Http\Controllers\Retail;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Retail\RetailCompany;
use App\Models\Retail\RetailCompanySetting;
use App\Models\Retail\RetailSupplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RetailSettingController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $selectedCompany = $this->selectedCompany($request);
        $selectedCompanyKey = $selectedCompany?->company_key;

        if (! $selectedCompany instanceof RetailCompany) {
            return redirect()->route('retail.system.manage')->withErrors(['company' => '会社を1件以上登録してください。']);
        }

        return view('retail.settings.index', [
            'breweryCustomers' => Customer::query()
                ->where('is_active', true)
                ->orderBy('customer_code')
                ->get(['id', 'customer_code', 'name']),
            'brewerySupplier' => RetailSupplier::query()->firstOrCreate(
                ['supplier_code' => 'BREWERY'],
                [
                    'name' => '蔵販売業務システム',
                    'supplier_type' => 'brewery',
                    'ordering_method' => 'api',
                    'is_active' => true,
                ],
            ),
            'companies' => RetailCompany::activeOptions(),
            'companyRecords' => RetailCompany::allForManagement(),
            'companySetting' => RetailCompanySetting::query()->firstOrCreate(
                ['company_key' => $selectedCompany->company_key],
                [
                    'sale_mode' => 'mixed',
                    'inventory_sales_policy' => 'strict_stock',
                    'delivery_note_policy' => 'on_demand',
                    'invoice_policy' => 'monthly_credit',
                    'brewery_procurement_policy' => 'auto_order',
                    'external_procurement_policy' => 'supplier_order',
                ],
            ),
            'selectedCompanyKey' => $selectedCompanyKey,
            'selectedCompany' => [
                'name' => $selectedCompany->name,
                'description' => $selectedCompany->description,
                'representative_name' => $selectedCompany->representative_name,
                'postal_code' => $selectedCompany->postal_code,
                'address1' => $selectedCompany->address1,
                'address2' => $selectedCompany->address2,
                'phone' => $selectedCompany->phone,
                'fax' => $selectedCompany->fax,
                'email' => $selectedCompany->email,
                'invoice_registration_number' => $selectedCompany->invoice_registration_number,
            ],
        ]);
    }

    private function selectedCompany(Request $request): ?RetailCompany
    {
        $companyKey = $request->session()->get('retail.company');
        $company = RetailCompany::activeByKey(is_string($companyKey) ? $companyKey : null);
        if ($company) {
            return $company;
        }

        $firstCompanyKey = array_key_first(RetailCompany::activeOptions());
        if ($firstCompanyKey === null) {
            return null;
        }

        $request->session()->put('retail.company', $firstCompanyKey);

        return RetailCompany::activeByKey($firstCompanyKey);
    }

    public function update(Request $request): RedirectResponse
    {
        $selectedCompany = $this->selectedCompany($request);

        if (! $selectedCompany instanceof RetailCompany) {
            return redirect()->route('retail.system.manage')->withErrors(['company' => '会社を1件以上登録してください。']);
        }

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:160'],
            'company_description' => ['nullable', 'string', 'max:255'],
            'representative_name' => ['nullable', 'string', 'max:160'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'address1' => ['nullable', 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'fax' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'invoice_registration_number' => ['nullable', 'string', 'max:20'],
            'sale_mode' => ['required', Rule::in(['mixed', 'cash_only', 'credit_enabled'])],
            'inventory_sales_policy' => ['required', Rule::in(['strict_stock', 'allow_negative_order'])],
            'delivery_note_policy' => ['required', Rule::in(['on_demand', 'per_sale'])],
            'invoice_policy' => ['required', Rule::in(['monthly_credit', 'per_invoice'])],
            'brewery_procurement_policy' => ['required', Rule::in(['auto_order'])],
            'external_procurement_policy' => ['required', Rule::in(['supplier_order', 'manual'])],
            'brewery_partner_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('is_active', true)],
        ]);

        $selectedCompany->forceFill([
            'name' => $validated['company_name'],
            'description' => $validated['company_description'],
            'representative_name' => $validated['representative_name'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'address1' => $validated['address1'] ?? null,
            'address2' => $validated['address2'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'fax' => $validated['fax'] ?? null,
            'email' => $validated['email'] ?? null,
            'invoice_registration_number' => $validated['invoice_registration_number'] ?? null,
        ])->save();

        RetailCompanySetting::query()->firstOrCreate(
            ['company_key' => $selectedCompany->company_key],
            [
                'sale_mode' => 'mixed',
                'inventory_sales_policy' => 'strict_stock',
                'delivery_note_policy' => 'on_demand',
                'invoice_policy' => 'monthly_credit',
                'brewery_procurement_policy' => 'auto_order',
                'external_procurement_policy' => 'supplier_order',
            ],
        )->forceFill([
            'sale_mode' => $validated['sale_mode'],
            'inventory_sales_policy' => $validated['inventory_sales_policy'],
            'delivery_note_policy' => $validated['delivery_note_policy'],
            'invoice_policy' => $validated['invoice_policy'],
            'brewery_procurement_policy' => $validated['brewery_procurement_policy'],
            'external_procurement_policy' => $validated['external_procurement_policy'],
        ])->save();

        RetailSupplier::query()->firstOrCreate(
            ['supplier_code' => 'BREWERY'],
            [
                'name' => '蔵販売業務システム',
                'supplier_type' => 'brewery',
                'ordering_method' => 'api',
                'is_active' => true,
            ],
        )->forceFill([
            'name' => '蔵販売業務システム',
            'supplier_type' => 'brewery',
            'ordering_method' => 'api',
            'brewery_partner_id' => $validated['brewery_partner_id'] ?? null,
            'is_active' => true,
        ])->save();

        return redirect()->route('retail.settings')->with('status', '設定を保存しました。');
    }
}
