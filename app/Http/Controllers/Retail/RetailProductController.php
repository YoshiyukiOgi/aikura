<?php

namespace App\Http\Controllers\Retail;

use App\Http\Controllers\Controller;
use App\Models\Retail\RetailInventoryMovement;
use App\Models\Retail\RetailInventoryStock;
use App\Models\Retail\RetailProduct;
use App\Models\Retail\RetailSupplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RetailProductController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
            'edit' => ['nullable', 'integer', 'min:1'],
        ]);

        $products = RetailProduct::query()
            ->with(['supplier:id,name,supplier_type', 'inventoryStock:retail_product_id,quantity'])
            ->where('procurement_source', 'external')
            ->when(($validated['q'] ?? '') !== '', function (Builder $query) use ($validated): void {
                $search = '%'.trim((string) $validated['q']).'%';
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('product_code', 'ilike', $search)
                        ->orWhere('name', 'ilike', $search)
                        ->orWhere('name_kana', 'ilike', $search);
                });
            })
            ->when(($validated['active'] ?? 'active') === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when(($validated['active'] ?? 'active') === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->orderBy('product_code')
            ->paginate(30)
            ->withQueryString();

        $editingProduct = null;
        if (isset($validated['edit'])) {
            $editingProduct = RetailProduct::query()
                ->where('procurement_source', 'external')
                ->with(['supplier:id,name,supplier_type', 'inventoryStock'])
                ->find($validated['edit']);
        }

        return view('retail.products.index', [
            'products' => $products,
            'editingProduct' => $editingProduct,
            'suppliers' => RetailSupplier::query()
                ->where('supplier_type', 'external')
                ->where('is_active', true)
                ->orderBy('supplier_code')
                ->get(['id', 'supplier_code', 'name', 'supplier_type']),
            'filters' => [
                'q' => $validated['q'] ?? '',
                'active' => $validated['active'] ?? 'active',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_code' => ['required', 'string', 'max:80', Rule::unique('retail.retail_products', 'product_code')],
            'name' => ['required', 'string', 'max:160'],
            'name_kana' => ['nullable', 'string', 'max:160'],
            'retail_supplier_id' => [
                'nullable',
                Rule::exists('retail.retail_suppliers', 'id')->where('supplier_type', 'external'),
            ],
            'new_supplier_code' => ['nullable', 'alpha_dash', 'max:80', Rule::unique('retail.retail_suppliers', 'supplier_code')],
            'new_supplier_name' => ['nullable', 'string', 'max:160'],
            'cost_price' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'stock_unit' => ['required', 'string', 'max:30'],
            'reorder_point' => ['nullable', 'numeric', 'min:0', 'max:99999999999.999'],
            'reorder_quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999999.999'],
            'stock_quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999999.999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (empty($validated['retail_supplier_id']) && (empty($validated['new_supplier_code']) || empty($validated['new_supplier_name']))) {
            return back()
                ->withInput()
                ->withErrors(['retail_supplier_id' => '外部仕入先を選択するか、新規仕入先コードと名称を入力してください。']);
        }

        $product = DB::connection(config('retail.database.connection', 'retail'))->transaction(function () use ($request, $validated): RetailProduct {
            $supplierId = $validated['retail_supplier_id'] ?? null;
            if ($supplierId === null) {
                $supplier = RetailSupplier::query()->create([
                    'supplier_code' => $validated['new_supplier_code'],
                    'name' => $validated['new_supplier_name'],
                    'supplier_type' => 'external',
                    'ordering_method' => 'manual',
                    'is_active' => true,
                ]);
                $supplierId = $supplier->id;
            }

            $product = RetailProduct::query()->create([
                'product_code' => $validated['product_code'],
                'name' => $validated['name'],
                'name_kana' => $validated['name_kana'] ?? null,
                'procurement_source' => 'external',
                'retail_supplier_id' => $supplierId,
                'cost_price' => $validated['cost_price'],
                'selling_price' => $validated['selling_price'],
                'tax_rate' => $validated['tax_rate'],
                'stock_unit' => $validated['stock_unit'],
                'reorder_point' => $validated['reorder_point'] ?? null,
                'reorder_quantity' => $validated['reorder_quantity'] ?? null,
                'is_active' => $request->boolean('is_active', true),
            ]);

            if (($validated['stock_quantity'] ?? null) !== null) {
                RetailInventoryStock::query()->create([
                    'retail_product_id' => $product->id,
                    'quantity' => $validated['stock_quantity'],
                ]);
                RetailInventoryMovement::query()->create([
                    'retail_product_id' => $product->id,
                    'movement_type' => 'adjustment',
                    'quantity' => $validated['stock_quantity'],
                    'stock_after' => $validated['stock_quantity'],
                    'occurred_at' => now(),
                    'note' => '外部商品追加時の初期在庫',
                ]);
            }

            return $product;
        });

        return redirect()
            ->route('retail.products.index', ['edit' => $product->id])
            ->with('status', '外部商品を追加しました。');
    }

    public function update(Request $request, RetailProduct $retailProduct): RedirectResponse
    {
        if ($retailProduct->procurement_source !== 'external') {
            return redirect()
                ->route('retail.products.index')
                ->withErrors(['product' => '蔵商品は小売側の「取扱商品選択」画面から管理してください。']);
        }

        $validated = $request->validate([
            'product_code' => [
                'required',
                'string',
                'max:80',
                Rule::unique('retail.retail_products', 'product_code')->ignore($retailProduct->id),
            ],
            'name' => ['required', 'string', 'max:160'],
            'name_kana' => ['nullable', 'string', 'max:160'],
            'retail_supplier_id' => [
                'required',
                Rule::exists('retail.retail_suppliers', 'id')->where('supplier_type', 'external'),
            ],
            'cost_price' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'stock_unit' => ['required', 'string', 'max:30'],
            'reorder_point' => ['nullable', 'numeric', 'min:0', 'max:99999999999.999'],
            'reorder_quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999999.999'],
            'stock_quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999999.999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DB::connection(config('retail.database.connection', 'retail'))->transaction(function () use ($request, $retailProduct, $validated): void {
            $stockQuantity = $validated['stock_quantity'] ?? null;
            unset($validated['stock_quantity']);

            $retailProduct->update([
                ...$validated,
                'is_active' => $request->boolean('is_active'),
            ]);

            if ($stockQuantity !== null) {
                $stock = RetailInventoryStock::query()->firstOrCreate(
                    ['retail_product_id' => $retailProduct->id],
                    ['quantity' => 0],
                );
                $before = (float) $stock->quantity;
                $after = (float) $stockQuantity;
                $diff = $after - $before;

                if (abs($diff) > 0.0001) {
                    $stock->update(['quantity' => $after]);
                    RetailInventoryMovement::query()->create([
                        'retail_product_id' => $retailProduct->id,
                        'movement_type' => 'adjustment',
                        'quantity' => $diff,
                        'stock_after' => $after,
                        'occurred_at' => now(),
                        'note' => '商品マスタから現在庫を調整',
                    ]);
                }
            }
        });

        return redirect()
            ->route('retail.products.index', ['edit' => $retailProduct->id])
            ->with('status', '外部商品を更新しました。');
    }
}
