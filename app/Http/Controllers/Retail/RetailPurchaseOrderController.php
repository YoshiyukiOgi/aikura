<?php

namespace App\Http\Controllers\Retail;

use App\Http\Controllers\Controller;
use App\Models\Retail\RetailCompany;
use App\Models\Retail\RetailPurchaseOrder;
use App\Services\Retail\CancelRetailPurchaseOrderService;
use App\Services\Retail\CreateRetailPurchaseOrderSuggestionsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RetailPurchaseOrderController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $selectedCompanyKey = $request->session()->get('retail.company');
        $selectedCompany = RetailCompany::activeByKey(is_string($selectedCompanyKey) ? $selectedCompanyKey : null);

        if (! $selectedCompany instanceof RetailCompany) {
            return redirect()->route('retail.companies.index');
        }

        return view('retail.purchase-orders.index', [
            'companies' => RetailCompany::activeOptions(),
            'selectedCompanyKey' => $selectedCompanyKey,
            'selectedCompany' => [
                'name' => $selectedCompany->name,
                'description' => $selectedCompany->description,
            ],
            'manualRequiredCount' => RetailPurchaseOrder::query()
                ->where('brewery_cancel_status', 'manual_required')
                ->count(),
            'orders' => RetailPurchaseOrder::query()->with('supplier')->latest('created_at')->paginate(30),
        ]);
    }

    public function createSuggestions(CreateRetailPurchaseOrderSuggestionsService $service): RedirectResponse
    {
        $orders = $service->create();

        return redirect()->route('retail.purchase-orders.index')->with('status', $orders->count().'件の発注案を作成しました。');
    }

    public function sendBrewery(RetailPurchaseOrder $retailPurchaseOrder, CreateRetailPurchaseOrderSuggestionsService $service): RedirectResponse
    {
        $service->sendToBrewery($retailPurchaseOrder);

        return redirect()->route('retail.purchase-orders.index')->with('status', '蔵APIへ発注送信しました。');
    }

    public function destroy(RetailPurchaseOrder $retailPurchaseOrder, CancelRetailPurchaseOrderService $service): RedirectResponse
    {
        $service->deleteDraft($retailPurchaseOrder);

        return redirect()->route('retail.purchase-orders.index')->with('status', '未送信の発注案を削除しました。');
    }

    public function cancel(Request $request, RetailPurchaseOrder $retailPurchaseOrder, CancelRetailPurchaseOrderService $service): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $service->cancel($retailPurchaseOrder, $validated['reason']);

        return redirect()->route('retail.purchase-orders.index')->with('status', '発注を取消済みにしました。');
    }
}
