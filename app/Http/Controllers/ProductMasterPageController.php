<?php

namespace App\Http\Controllers;

use App\Models\ConsumptionTaxCategory;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductMasterPageController extends Controller
{
    public function index(Request $request, AuthorizationService $authorizationService): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('masters.products.index', [
            'user' => $user,
            'canEdit' => $authorizationService->can($user, 'product_master.edit'),
            'canChangePrice' => $authorizationService->can($user, 'price.change'),
            'units' => Unit::query()->where('is_active', true)->orderBy('id')->get(['id', 'code', 'name', 'symbol', 'unit_type']),
            'taxCategories' => ConsumptionTaxCategory::query()->where('is_active', true)->orderBy('id')->get(['id', 'name', 'is_reduced_rate']),
            'productCategories' => Product::query()->whereNotNull('category_name')->where('category_name', '<>', '')->distinct()->orderBy('category_name')->pluck('category_name'),
        ]);
    }
}
