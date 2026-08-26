<?php

namespace App\Http\Controllers\Retail;

use App\Http\Controllers\Controller;
use App\Models\Retail\RetailCompany;
use App\Models\Retail\RetailCompanySetting;
use App\Models\Retail\RetailCustomer;
use App\Models\Retail\RetailInvoice;
use App\Models\Retail\RetailSale;
use App\Services\Retail\CancelRetailInvoiceService;
use App\Services\Retail\CreateRetailInvoiceService;
use App\Services\Retail\ResolveRetailBillingMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RetailInvoiceController extends Controller
{
    public function index(Request $request, ResolveRetailBillingMethod $billingMethods): View
    {
        $companySetting = $this->companySetting($request);
        $company = $this->company($request);
        $customers = RetailCustomer::query()
            ->availableToCompany($company->id)
            ->where('is_active', true)
            ->orderBy('customer_code')
            ->get();

        return view('retail.invoices.index', [
            'invoices' => RetailInvoice::query()->with('customer')->latest('invoice_date')->paginate(30),
            'customers' => $customers,
            'customerBillingMethods' => $customers->mapWithKeys(fn (RetailCustomer $customer): array => [
                $customer->id => $billingMethods->resolve($customer, $companySetting),
            ]),
            'sales' => RetailSale::query()
                ->with('customer:id,customer_code,name')
                ->where('sale_type', 'credit')
                ->where('status', '<>', 'cancelled')
                ->whereNull('closed_at')
                ->orderBy('sale_date')
                ->get(['id', 'sale_no', 'retail_customer_id', 'sale_date', 'total_amount']),
        ]);
    }

    public function store(Request $request, CreateRetailInvoiceService $service, ResolveRetailBillingMethod $billingMethods): RedirectResponse
    {
        $validated = $request->validate([
            'retail_customer_id' => ['required', Rule::exists('retail.retail_customers', 'id')],
            'retail_sale_id' => ['nullable', Rule::exists('retail.retail_sales', 'id')],
            'invoice_date' => ['required', 'date'],
            'closing_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $customer = RetailCustomer::query()->findOrFail($validated['retail_customer_id']);
        $validated['billing_method'] = $billingMethods->resolve($customer, $this->companySetting($request));

        if ($validated['billing_method'] === 'per_sale' && empty($validated['retail_sale_id'])) {
            return back()->withInput()->withErrors(['retail_sale_id' => '都度請求では対象の販売伝票を選択してください。']);
        }

        $invoice = $service->createForCustomer($customer, $validated);

        return redirect()->route('retail.invoices.show', $invoice)->with('status', '請求書を作成しました。');
    }

    public function show(RetailInvoice $retailInvoice): View
    {
        return view('retail.invoices.show', ['invoice' => $retailInvoice->load(['customer', 'lines.sale'])]);
    }

    public function cancel(Request $request, RetailInvoice $retailInvoice, CancelRetailInvoiceService $service): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $invoice = $service->cancel($retailInvoice, $validated['reason']);

        return redirect()->route('retail.invoices.show', $invoice)->with('status', '請求書を取消しました。対象販売は再締め可能です。');
    }

    private function companySetting(Request $request): RetailCompanySetting
    {
        $companyKey = $request->session()->get('retail.company');

        return RetailCompanySetting::query()->firstOrCreate(
            ['company_key' => is_string($companyKey) ? $companyKey : 'default'],
            [
                'sale_mode' => 'mixed',
                'inventory_sales_policy' => 'strict_stock',
                'delivery_note_policy' => 'on_demand',
                'invoice_policy' => 'monthly_credit',
                'brewery_procurement_policy' => 'auto_order',
                'external_procurement_policy' => 'supplier_order',
            ],
        );
    }

    private function company(Request $request): RetailCompany
    {
        $companyKey = $request->session()->get('retail.company');

        return RetailCompany::activeByKey(is_string($companyKey) ? $companyKey : null)
            ?? RetailCompany::query()->where('is_active', true)->orderBy('id')->firstOrFail();
    }
}
