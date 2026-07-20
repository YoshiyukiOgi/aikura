<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Customer;
use App\Models\Product;
use App\Services\Authorization\AuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class SalesOrderPageController extends Controller
{
    public function index(Request $request, AuthorizationService $authorizationService): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('sales-orders.index', [
            'user' => $user,
            'canCreate' => $authorizationService->can($user, 'sales_order.create'),
            'canUpdate' => $authorizationService->can($user, 'sales_order.update'),
            'canCancel' => $authorizationService->can($user, 'sales_order.cancel'),
            'canChangePrice' => $authorizationService->can($user, 'price.change'),
            'customers' => Cache::remember('sales_order_page.customers', now()->addMinutes(5), fn () => Customer::query()
                ->where('is_active', true)
                ->orderBy('customer_code')
                ->get(['id', 'customer_code', 'name'])
                ->map(fn (Customer $customer): array => ['id' => $customer->id, 'label' => "{$customer->customer_code} {$customer->name}"])
                ->values()),
            'products' => Cache::remember('sales_order_page.products', now()->addMinutes(5), fn () => Product::query()
                ->with('salesUnit:id,code,name')
                ->where('is_active', true)
                ->where('is_sales_available', true)
                ->orderBy('product_code')
                ->get(['id', 'product_code', 'display_name', 'sales_unit_id'])
                ->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'label' => "{$product->product_code} {$product->display_name}",
                    'unit_id' => $product->sales_unit_id,
                    'unit_code' => $product->salesUnit?->code,
                    'unit_name' => $product->salesUnit?->name,
                ])
                ->values()),
        ]);
    }
}
