<?php

namespace App\Http\Controllers\Retail;

use App\Http\Controllers\Controller;
use App\Models\Retail\RetailCompany;
use App\Models\Retail\RetailCustomer;
use App\Models\Retail\RetailPayment;
use App\Services\Retail\RefundRetailPaymentService;
use App\Services\Retail\RegisterRetailPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RetailPaymentController extends Controller
{
    public function index(Request $request): View
    {
        $companyKey = $request->session()->get('retail.company');
        $company = RetailCompany::activeByKey(is_string($companyKey) ? $companyKey : null)
            ?? RetailCompany::query()->where('is_active', true)->orderBy('id')->firstOrFail();

        return view('retail.payments.index', [
            'payments' => RetailPayment::query()->with('customer')->latest('payment_date')->paginate(30),
            'customers' => RetailCustomer::query()
                ->availableToCompany($company->id)
                ->where('is_active', true)
                ->orderBy('customer_code')
                ->get(),
        ]);
    }

    public function store(Request $request, RegisterRetailPaymentService $service): RedirectResponse
    {
        $validated = $request->validate([
            'retail_customer_id' => ['required', Rule::exists('retail.retail_customers', 'id')],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', Rule::in(['cash', 'bank_transfer', 'card', 'qr', 'other'])],
            'amount' => ['required', 'numeric', 'min:1', 'max:999999999999.99'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $payment = $service->register(RetailCustomer::query()->findOrFail($validated['retail_customer_id']), $validated);

        return redirect()->route('retail.payments.show', $payment)->with('status', '入金を登録し、未収請求へ消込しました。');
    }

    public function show(RetailPayment $retailPayment): View
    {
        return view('retail.payments.show', ['payment' => $retailPayment->load(['customer', 'allocations.invoice', 'adjustmentPayments', 'originalPayment'])]);
    }

    public function refund(Request $request, RetailPayment $retailPayment, RefundRetailPaymentService $service): RedirectResponse
    {
        $validated = $request->validate([
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', Rule::in(['cash', 'bank_transfer', 'card', 'qr', 'other'])],
            'amount' => ['required', 'numeric', 'min:1', 'max:999999999999.99'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $refund = $service->refund($retailPayment, $validated);

        return redirect()->route('retail.payments.show', $refund)->with('status', '返金を登録しました。');
    }
}
