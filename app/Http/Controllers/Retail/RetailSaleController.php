<?php

namespace App\Http\Controllers\Retail;

use App\Http\Controllers\Controller;
use App\Models\Retail\RetailCompany;
use App\Models\Retail\RetailCompanySetting;
use App\Models\Retail\RetailCustomer;
use App\Models\Retail\RetailProduct;
use App\Models\Retail\RetailSale;
use App\Services\Retail\AdjustRetailSaleService;
use App\Services\Retail\CreateRetailSaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RetailSaleController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $selectedCompany = $this->selectedCompany($request);
        $selectedCompanyKey = $selectedCompany?->company_key;

        if (! $selectedCompany instanceof RetailCompany) {
            return redirect()->route('retail.system.manage')->withErrors(['company' => '会社を1件以上登録してください。']);
        }

        $products = RetailProduct::query()
            ->with('inventoryStock:retail_product_id,quantity')
            ->where('is_active', true)
            ->orderBy('product_code')
            ->get();
        $companySetting = RetailCompanySetting::query()->firstOrCreate(
            ['company_key' => $selectedCompany->company_key],
            [
                'sale_mode' => 'mixed',
                'inventory_sales_policy' => 'strict_stock',
                'delivery_note_policy' => 'on_demand',
                'invoice_policy' => 'monthly_credit',
                'brewery_procurement_policy' => 'auto_order',
                'external_procurement_policy' => 'supplier_order',
            ],
        );
        $customers = RetailCustomer::query()
            ->availableToCompany($selectedCompany->id)
            ->where('is_active', true)
            ->orderBy('customer_code')
            ->get(['id', 'customer_code', 'name', 'name_kana', 'phone']);

        return view('retail.sales.create', [
            'companies' => RetailCompany::activeOptions(),
            'selectedCompanyKey' => $selectedCompanyKey,
            'selectedCompany' => [
                'name' => $selectedCompany->name,
                'description' => $selectedCompany->description,
            ],
            'customers' => $customers,
            'customerOptions' => $customers->map(fn (RetailCustomer $customer): array => [
                'id' => $customer->id,
                'code' => $customer->customer_code,
                'name' => $customer->name,
                'kana' => $customer->name_kana,
                'phone' => $customer->phone,
            ])->values(),
            'products' => $products,
            'companySetting' => $companySetting,
            'productOptions' => $products->map(fn (RetailProduct $product): array => [
                'id' => $product->id,
                'code' => $product->product_code,
                'name' => $product->name,
                'kana' => $product->name_kana,
                'source' => $product->procurement_source,
                'sourceLabel' => $product->procurement_source === 'brewery' ? '蔵商品' : '外部商品',
                'price' => (float) $product->selling_price,
                'taxRate' => (float) $product->tax_rate,
                'stock' => (float) ($product->inventoryStock?->quantity ?? 0),
                'unit' => $product->stock_unit,
                'brewerySourceStatus' => $product->brewery_source_status ?? 'current',
                'sourceWarning' => match ($product->brewery_source_status) {
                    'deleted' => '蔵側削除済みです。店舗在庫がある数量まで販売できます。蔵への発注は行いません。',
                    'inactive' => '蔵側販売停止です。店舗在庫がある数量まで販売できます。蔵への発注は行いません。',
                    'changed' => '蔵側で商品情報が変更されています。取扱商品選択で再追加内容を確認してください。',
                    default => null,
                },
            ])->values(),
        ]);
    }

    public function index(Request $request): View
    {
        $selectedCompany = $this->selectedCompany($request);
        $selectedCompanyKey = $selectedCompany?->company_key;
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', Rule::in(['posted', 'revised', 'cancelled', 'credit_note'])],
            'sale_type' => ['nullable', Rule::in(['cash', 'credit', 'card', 'qr'])],
        ]);

        $term = trim((string) ($filters['q'] ?? ''));
        $sales = RetailSale::query()
            ->with(['customer', 'items', 'deliveries', 'invoiceLines'])
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(function ($query) use ($term): void {
                    $query->where('sale_no', 'like', "%{$term}%")
                        ->orWhereHas('customer', fn ($customerQuery) => $customerQuery
                            ->where('customer_code', 'like', "%{$term}%")
                            ->orWhere('name', 'like', "%{$term}%")
                            ->orWhere('name_kana', 'like', "%{$term}%"))
                        ->orWhereHas('items', fn ($itemQuery) => $itemQuery
                            ->where('description', 'like', "%{$term}%"));
                });
            })
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('sale_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('sale_date', '<=', $date))
            ->when(($filters['status'] ?? null) === 'credit_note', fn ($query) => $query->where('correction_type', 'credit_note'))
            ->when(($filters['status'] ?? null) && $filters['status'] !== 'credit_note', fn ($query) => $query
                ->where('status', $filters['status'])
                ->whereNull('correction_type'))
            ->when($filters['sale_type'] ?? null, fn ($query, string $saleType) => $query->where('sale_type', $saleType))
            ->latest('sale_date')
            ->latest('id')
            ->paginate(30)
            ->appends($filters);

        return view('retail.sales.index', [
            'companies' => RetailCompany::activeOptions(),
            'selectedCompanyKey' => $selectedCompanyKey,
            'selectedCompany' => [
                'name' => $selectedCompany?->name ?? '小売会社',
                'description' => $selectedCompany?->description ?? '',
            ],
            'sales' => $sales,
            'filters' => $filters,
        ]);
    }

    public function store(Request $request, CreateRetailSaleService $service): RedirectResponse
    {
        $selectedCompany = $this->selectedCompany($request);
        if (! $selectedCompany instanceof RetailCompany) {
            return redirect()->route('retail.system.manage')->withErrors(['company' => '会社を1件以上登録してください。']);
        }

        $selectedCompanyKey = $selectedCompany->company_key;
        $companySetting = RetailCompanySetting::query()->firstOrCreate(
            ['company_key' => $selectedCompanyKey],
            [
                'sale_mode' => 'mixed',
                'inventory_sales_policy' => 'strict_stock',
                'delivery_note_policy' => 'on_demand',
                'invoice_policy' => 'monthly_credit',
                'brewery_procurement_policy' => 'auto_order',
                'external_procurement_policy' => 'supplier_order',
            ],
        );

        $request->merge([
            'items' => collect($request->input('items', []))
                ->filter(fn (array $item): bool => abs((float) ($item['quantity'] ?? 0)) > 0.0001)
                ->values()
                ->all(),
        ]);

        $validated = $request->validate([
            'sale_date' => ['required', 'date'],
            'sale_type' => ['required', Rule::in(['cash', 'credit', 'card', 'qr'])],
            'retail_customer_id' => ['nullable', Rule::exists('retail.retail_customers', 'id')],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.retail_product_id' => ['required', Rule::exists('retail.retail_products', 'id')],
            'items.*.quantity' => ['required', 'numeric', 'between:-999999.999,999999.999', 'not_in:0'],
        ]);

        if ($validated['sale_type'] === 'credit' && empty($validated['retail_customer_id'])) {
            return back()
                ->withInput()
                ->withErrors(['retail_customer_id' => '掛売の場合は小売顧客を選択してください。']);
        }

        $validated['retail_company_id'] = $selectedCompany->id;
        $validated['allow_negative_stock'] = $companySetting->inventory_sales_policy === 'allow_negative_order';
        $sale = $service->create($validated, $validated['items']);

        return redirect()
            ->route('retail.sales.show', $sale)
            ->with('status', '販売を登録し、在庫を減算しました。');
    }

    public function show(RetailSale $retailSale): View
    {
        return view('retail.sales.show', [
            'sale' => $retailSale->load(['customer', 'items.product', 'deliveries', 'invoiceLines', 'correctionSales', 'originalSale']),
            'products' => RetailProduct::query()
                ->with('inventoryStock:retail_product_id,quantity')
                ->where('is_active', true)
                ->orderBy('product_code')
                ->get(),
        ]);
    }

    public function revise(Request $request, RetailSale $retailSale, AdjustRetailSaleService $service): RedirectResponse
    {
        $selectedCompanyKey = RetailCompany::query()
            ->whereKey($retailSale->retail_company_id)
            ->value('company_key')
            ?? $request->session()->get('retail.company');
        $companySetting = RetailCompanySetting::query()->firstOrCreate(
            ['company_key' => is_string($selectedCompanyKey) ? $selectedCompanyKey : 'default'],
            [
                'sale_mode' => 'mixed',
                'inventory_sales_policy' => 'strict_stock',
                'delivery_note_policy' => 'on_demand',
                'invoice_policy' => 'monthly_credit',
                'brewery_procurement_policy' => 'auto_order',
                'external_procurement_policy' => 'supplier_order',
            ],
        );

        $request->merge([
            'items' => collect($request->input('items', []))
                ->filter(fn (array $item): bool => (float) ($item['quantity'] ?? 0) > 0)
                ->values()
                ->all(),
        ]);

        $validated = $request->validate([
            'sale_date' => ['required', 'date'],
            'sale_type' => ['required', Rule::in(['cash', 'credit', 'card', 'qr'])],
            'retail_customer_id' => ['nullable', Rule::exists('retail.retail_customers', 'id')],
            'note' => ['nullable', 'string', 'max:1000'],
            'reason' => ['required', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.retail_product_id' => ['required', Rule::exists('retail.retail_products', 'id')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999999'],
        ]);

        if ($validated['sale_type'] === 'credit' && empty($validated['retail_customer_id'])) {
            return back()->withInput()->withErrors(['retail_customer_id' => '掛売の場合は小売顧客を選択してください。']);
        }

        $validated['allow_negative_stock'] = $companySetting->inventory_sales_policy === 'allow_negative_order';
        $sale = $service->revise($retailSale, $validated, $validated['items']);

        return redirect()->route('retail.sales.show', $sale)->with('status', '締め前販売を変更し、在庫差分を反映しました。');
    }

    public function cancel(Request $request, RetailSale $retailSale, AdjustRetailSaleService $service): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $sale = $service->cancel($retailSale, $validated['reason']);

        return redirect()->route('retail.sales.show', $sale)->with('status', '締め前販売を取消し、在庫を戻しました。');
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

    public function creditNote(Request $request, RetailSale $retailSale, AdjustRetailSaleService $service): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $credit = $service->createCreditNote($retailSale, $validated['reason']);

        return redirect()->route('retail.sales.show', $credit)->with('status', '締め後販売の赤伝を作成し、在庫を戻しました。');
    }
}
