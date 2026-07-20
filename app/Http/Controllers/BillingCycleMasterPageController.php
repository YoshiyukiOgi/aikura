<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingCycleMasterPageController extends Controller
{
    public function index(Request $request, AuthorizationService $authorizationService): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('masters.billing-cycles.index', [
            'user' => $user,
            'canEdit' => $authorizationService->can($user, 'billing_cycle_master.edit'),
        ]);
    }
}
