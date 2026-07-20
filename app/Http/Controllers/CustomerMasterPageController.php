<?php

namespace App\Http\Controllers;

use App\Models\BillingCycle;
use App\Models\SettlementReceivableCategory;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerMasterPageController extends Controller
{
    public function index(Request $request, AuthorizationService $authorizationService): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('masters.customers.index', [
            'user' => $user,
            'canEdit' => $authorizationService->can($user, 'customer_master.edit'),
            'transactionCategories' => TransactionCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(['id', 'name']),
            'settlementCategories' => SettlementReceivableCategory::query()->where('is_active', true)->orderBy('id')->get(['id', 'name']),
            'billingCycles' => BillingCycle::query()->where('is_active', true)->orderBy('id')->get(['id', 'name']),
        ]);
    }
}
