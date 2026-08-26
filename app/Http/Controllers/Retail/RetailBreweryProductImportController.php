<?php

namespace App\Http\Controllers\Retail;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Retail\RetailBreweryProductImportSelection;
use App\Models\Retail\RetailPriceChangeCandidate;
use App\Models\Retail\RetailPriceHistory;
use App\Models\Retail\RetailPriceSyncSetting;
use App\Models\Retail\RetailProduct;
use App\Models\Retail\RetailSupplier;
use App\Services\Retail\ApplyRetailPriceChangeCandidateService;
use App\Services\Retail\DetectRetailBreweryProductChangesService;
use App\Services\Retail\RetailBreweryProductPriceResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RetailBreweryProductImportController extends Controller
{
    public function __construct(private readonly RetailBreweryProductPriceResolver $priceResolver) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['unimported', 'imported', 'all'])],
            'tab' => ['nullable', Rule::in(['import', 'candidates', 'history'])],
        ]);

        $importedProductIds = RetailProduct::query()
            ->where('procurement_source', 'brewery')
            ->whereNotNull('brewery_product_id')
            ->pluck('brewery_product_id')
            ->all();

        $products = Product::query()
            ->with(['salesUnit:id,name,symbol', 'inventoryUnit:id,name,symbol', 'capacityUnit:id,name,symbol'])
            ->where('is_active', true)
            ->where('is_sales_available', true)
            ->when(($validated['category'] ?? '') !== '', function (Builder $query) use ($validated): void {
                $category = trim((string) $validated['category']);
                $query->where(function (Builder $query) use ($category): void {
                    $query->where('category_name', $category)
                        ->orWhere(function (Builder $query) use ($category): void {
                            $query->whereNull('category_name')
                                ->where('product_type', $category);
                        });
                });
            })
            ->when(($validated['q'] ?? '') !== '', function (Builder $query) use ($validated): void {
                $search = '%'.trim((string) $validated['q']).'%';
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('product_code', 'ilike', $search)
                        ->orWhere('name', 'ilike', $search)
                        ->orWhere('display_name', 'ilike', $search)
                        ->orWhere('brand_name', 'ilike', $search)
                        ->orWhere('category_name', 'ilike', $search);
                });
            })
            ->when(($validated['status'] ?? 'all') === 'unimported', fn (Builder $query) => $query->whereNotIn('id', $importedProductIds ?: [0]))
            ->when(($validated['status'] ?? 'all') === 'imported', fn (Builder $query) => $query->whereIn('id', $importedProductIds ?: [0]))
            ->orderBy('product_code')
            ->paginate(60)
            ->withQueryString();
        $selectedProductIds = RetailBreweryProductImportSelection::query()
            ->where('is_selected', true)
            ->pluck('brewery_product_id')
            ->all();

        return view('retail.products.import', [
            'categoryOptions' => Product::query()
                ->where('is_active', true)
                ->where('is_sales_available', true)
                ->get(['category_name', 'product_type'])
                ->map(fn (Product $product): string => $product->category_name ?: $product->product_type)
                ->filter()
                ->unique()
                ->sort()
                ->values(),
            'products' => $products,
            'importedProductIds' => array_flip($importedProductIds),
            'selectedProductIds' => array_flip($selectedProductIds),
            'priceCandidates' => RetailPriceChangeCandidate::query()
                ->with('product:id,product_code,name,cost_price,selling_price')
                ->where('status', 'open')
                ->latest('detected_at')
                ->limit(50)
                ->get(),
            'priceHistories' => RetailPriceHistory::query()
                ->with('product:id,product_code,name')
                ->latest('applied_at')
                ->limit(10)
                ->get(),
            'priceSyncSetting' => RetailPriceSyncSetting::query()->firstOrCreate([], ['detection_mode' => 'manual']),
            'sourceAlerts' => RetailProduct::query()
                ->where('procurement_source', 'brewery')
                ->whereIn('brewery_source_status', ['changed', 'inactive', 'deleted'])
                ->orderBy('product_code')
                ->get(),
            'filters' => [
                'q' => $validated['q'] ?? '',
                'category' => $validated['category'] ?? '',
                'status' => $validated['status'] ?? 'all',
                'tab' => $validated['tab'] ?? 'import',
            ],
        ]);
    }

    public function store(Product $product): RedirectResponse
    {
        if (! $product->is_active || ! $product->is_sales_available) {
            return back()->withErrors(['product' => '販売可能な蔵商品だけ小売の取扱商品に追加できます。']);
        }

        DB::connection(config('retail.database.connection', 'retail'))->transaction(
            fn (): RetailProduct => $this->importProduct($product),
        );

        return redirect()
            ->route('retail.products.import')
            ->with('status', '小売の取扱商品に追加しました。初回追加では蔵価格を設定し、再追加では既存価格を変更しません。');
    }

    public function bulkStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'visible_product_ids' => ['nullable', 'array'],
            'visible_product_ids.*' => ['integer', Rule::exists('products', 'id')],
            'selected_product_ids' => ['nullable', 'array'],
            'selected_product_ids.*' => ['integer', Rule::exists('products', 'id')],
        ]);
        $visibleProductIds = collect($validated['visible_product_ids'] ?? [])->map(fn ($id): int => (int) $id)->unique();
        $selectedProductIds = collect($validated['selected_product_ids'] ?? [])->map(fn ($id): int => (int) $id)->unique();

        $this->saveSelections($visibleProductIds->all(), $selectedProductIds->all());

        if ($selectedProductIds->isEmpty()) {
            return redirect()
                ->route('retail.products.import', ['tab' => 'import'])
                ->withErrors(['products' => '取扱商品に追加する商品にチェックを入れてください。']);
        }

        $products = Product::query()
            ->with(['salesUnit:id,name,symbol'])
            ->whereIn('id', $selectedProductIds->all())
            ->where('is_active', true)
            ->where('is_sales_available', true)
            ->get();

        if ($products->isEmpty()) {
            return redirect()
                ->route('retail.products.import', ['tab' => 'import'])
                ->with('status', '追加対象の商品はありません。');
        }

        DB::connection(config('retail.database.connection', 'retail'))->transaction(function () use ($products): void {
            foreach ($products as $product) {
                $this->importProduct($product);
            }
        });

        return redirect()
            ->route('retail.products.import', ['tab' => 'import'])
            ->with('status', $products->count().'件を小売の取扱商品に追加しました。再追加した商品の既存価格は変更していません。');
    }

    public function updateSelections(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'visible_product_ids' => ['required', 'array'],
            'visible_product_ids.*' => ['integer', Rule::exists('products', 'id')],
            'selected_product_ids' => ['nullable', 'array'],
            'selected_product_ids.*' => ['integer', Rule::exists('products', 'id')],
        ]);

        $this->saveSelections($validated['visible_product_ids'], $validated['selected_product_ids'] ?? []);

        return redirect()
            ->route('retail.products.import', ['tab' => 'import'])
            ->with('status', '取扱商品に追加するチェック状態を保存しました。');
    }

    public function updatePriceSyncSetting(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'detection_mode' => ['required', Rule::in(['manual', 'daily', 'interval'])],
            'interval_minutes' => ['nullable', 'integer', 'min:15', 'max:1440'],
        ]);

        RetailPriceSyncSetting::query()->firstOrCreate([], ['detection_mode' => 'manual'])
            ->forceFill([
                'detection_mode' => $validated['detection_mode'],
                'interval_minutes' => $validated['interval_minutes'] ?? 60,
            ])
            ->save();

        return redirect()->route('retail.settings')->with('status', '価格差分検出の設定を保存しました。');
    }

    public function detectPriceChanges(DetectRetailBreweryProductChangesService $service): RedirectResponse
    {
        $summary = $service->detect();

        return redirect()
            ->route('retail.products.import', ['tab' => 'candidates'])
            ->with('status', "蔵商品情報を確認しました。新規 {$summary['new']}件、基本情報変更 {$summary['changed']}件、価格変更 {$summary['price']}件、販売停止 {$summary['inactive']}件、削除済み {$summary['deleted']}件です。");
    }

    public function applyPriceChange(
        Request $request,
        RetailPriceChangeCandidate $candidate,
        ApplyRetailPriceChangeCandidateService $service,
    ): RedirectResponse {
        $validated = $request->validate([
            'apply_mode' => ['required', Rule::in(['cost_only', 'cost_and_selling'])],
        ]);

        $service->apply($candidate, $validated['apply_mode']);

        return redirect()->route('retail.products.import', ['tab' => 'candidates'])->with('status', '価格改定候補を反映し、価格履歴を保存しました。');
    }

    /**
     * @param array<int, int> $visibleProductIds
     * @param array<int, int> $selectedProductIds
     */
    private function saveSelections(array $visibleProductIds, array $selectedProductIds): void
    {
        $selectedLookup = array_flip(array_map('intval', $selectedProductIds));

        foreach (array_unique(array_map('intval', $visibleProductIds)) as $productId) {
            RetailBreweryProductImportSelection::query()->updateOrCreate(
                ['brewery_product_id' => $productId],
                ['is_selected' => isset($selectedLookup[$productId])],
            );
        }
    }

    private function importProduct(Product $product): RetailProduct
    {
        $supplier = RetailSupplier::query()->firstOrCreate(
            ['supplier_code' => 'BREWERY'],
            [
                'name' => '蔵販売業務システム',
                'supplier_type' => 'brewery',
                'ordering_method' => 'api',
                'is_active' => true,
            ],
        );

        $prices = $this->priceResolver->resolve($product);

        $retailProduct = RetailProduct::query()->firstOrNew([
            'procurement_source' => 'brewery',
            'brewery_product_id' => $product->id,
        ]);
        $isNew = ! $retailProduct->exists;

        $retailProduct->fill([
            'product_code' => 'BR-'.$product->product_code,
            'name' => $product->display_name ?: $product->name,
            'name_kana' => $product->name_kana,
            'retail_supplier_id' => $supplier->id,
            'tax_rate' => $prices['tax_rate'],
            'stock_unit' => $product->salesUnit?->symbol ?: $product->salesUnit?->name ?: '本',
            'is_active' => true,
            'brewery_source_status' => 'current',
            'brewery_source_checked_at' => now(),
        ]);

        if ($isNew) {
            $retailProduct->fill([
                'cost_price' => $prices['cost_price'],
                'selling_price' => $prices['selling_price'],
            ]);
        }

        $retailProduct->save();

        return $retailProduct;
    }
}
