<?php

namespace App\Http\Controllers\Retail;

use App\Http\Controllers\Controller;
use App\Models\Retail\RetailDelivery;
use App\Models\Retail\RetailCompanySetting;
use App\Models\Retail\RetailSale;
use App\Services\Retail\CreateRetailDeliveryService;
use App\Services\Retail\ResolveRetailBillingMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RetailDeliveryController extends Controller
{
    public function index(): View
    {
        return view('retail.deliveries.index', [
            'deliveries' => RetailDelivery::query()->with(['customer', 'sale'])->latest('delivery_date')->paginate(30),
            'sales' => RetailSale::query()->with('customer')->latest('sale_date')->limit(50)->get(),
        ]);
    }

    public function store(Request $request, CreateRetailDeliveryService $service, ResolveRetailBillingMethod $billingMethods): RedirectResponse
    {
        $validated = $request->validate([
            'retail_sale_id' => ['required', Rule::exists('retail.retail_sales', 'id')],
            'delivery_date' => ['required', 'date'],
            'delivery_name' => ['nullable', 'string', 'max:160'],
            'delivery_postal_code' => ['nullable', 'string', 'max:20'],
            'delivery_address1' => ['nullable', 'string', 'max:255'],
            'delivery_address2' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $sale = RetailSale::query()->with('customer')->findOrFail($validated['retail_sale_id']);
        if ($sale->customer) {
            $validated['billing_method'] = $billingMethods->resolve($sale->customer, $this->companySetting($request));
        } else {
            $validated['billing_method'] = 'per_sale';
        }
        $delivery = $service->createFromSale($sale, $validated);

        return redirect()->route('retail.deliveries.show', $delivery)->with(
            'status',
            $delivery->wasRecentlyCreated ? '納品書を作成しました。' : '発行済みの納品書を表示しました。',
        );
    }

    public function show(RetailDelivery $retailDelivery): View
    {
        return view('retail.deliveries.show', ['delivery' => $retailDelivery->load(['customer', 'sale', 'lines.saleItem'])]);
    }

    public function cancel(Request $request, RetailDelivery $retailDelivery): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        DB::connection(config('retail.database.connection', 'retail'))->transaction(function () use ($retailDelivery, $validated): void {
            $delivery = RetailDelivery::query()->lockForUpdate()->findOrFail($retailDelivery->id);
            if ($delivery->status === 'cancelled') {
                throw ValidationException::withMessages(['delivery' => 'この納品書は既に取消済みです。']);
            }
            if ($delivery->status !== 'issued') {
                throw ValidationException::withMessages(['delivery' => '発行済みの納品書だけ取消できます。']);
            }

            $delivery->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $validated['reason'],
            ])->save();
        });

        return redirect()->route('retail.sales.show', $retailDelivery->retail_sale_id)->with('status', '納品書を取消・無効化しました。');
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
}
