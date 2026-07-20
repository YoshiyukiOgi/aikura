<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreProductFamilyRequest;
use App\Http\Requests\Api\V1\StoreProductVariantRequest;
use App\Http\Requests\Api\V1\UpdateProductFamilyRequest;
use App\Http\Requests\Api\V1\UpdateProductVariantRequest;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Services\Masters\SaveProductMasterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductMasterController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $this->validatedFilters($request);
        $paginator = $this->filteredQuery($validated)
            ->with(['products.capacityUnit:id,name,symbol'])
            ->withCount('products')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? 30);

        return $this->ok([
            'product_families' => collect($paginator->items())->map(fn (ProductFamily $family): array => $this->familySummary($family))->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(), 'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(ProductFamily $productFamily): JsonResponse
    {
        $productFamily->load(['consumptionTaxCategory:id,name', 'products.capacityUnit:id,name,symbol']);
        $history = AuditLog::query()->with('user:id,name')
            ->where('target_table', 'product_families')->where('target_id', (string) $productFamily->id)
            ->latest('occurred_at')->latest('id')->limit(10)->get()
            ->map(fn (AuditLog $log): array => [
                'event' => $log->event, 'occurred_at' => $log->occurred_at?->toIso8601String(),
                'user_name' => $log->user?->name, 'reason' => $log->reason,
            ]);

        return $this->ok(['product_family' => [
            ...$this->familySummary($productFamily),
            'name_kana' => $productFamily->name_kana, 'brand_name' => $productFamily->brand_name,
            'category_name' => $productFamily->category_name,
            'consumption_tax_category_id' => $productFamily->consumption_tax_category_id,
            'consumption_tax_category_name' => $productFamily->consumptionTaxCategory?->name,
            'alcohol_percentage' => $productFamily->alcohol_percentage,
            'liquor_tax_category_code' => $productFamily->liquor_tax_category_code,
            'liquor_type_name' => $productFamily->liquor_type_name, 'ingredients' => $productFamily->ingredients,
            'rice_polishing_ratio' => $productFamily->rice_polishing_ratio,
            'production_method' => $productFamily->production_method,
            'is_unpasteurized' => $productFamily->is_unpasteurized,
            'is_sales_available' => $productFamily->is_sales_available,
            'is_inventory_managed' => $productFamily->is_inventory_managed,
            'note' => $productFamily->note, 'history' => $history,
            'products' => $productFamily->products->map(fn (Product $product): array => $this->variant($product))->all(),
        ]]);
    }

    public function store(StoreProductFamilyRequest $request, SaveProductMasterService $service): JsonResponse
    {
        $family = $service->createFamily($request->validated());

        return $this->created(['product_family' => $this->familySummary($family->load('products.capacityUnit'))]);
    }

    public function update(UpdateProductFamilyRequest $request, ProductFamily $productFamily, SaveProductMasterService $service): JsonResponse
    {
        $family = $service->updateFamily($productFamily, $request->validated());

        return $this->ok(['product_family' => $this->familySummary($family->load('products.capacityUnit'))]);
    }

    public function storeVariant(StoreProductVariantRequest $request, ProductFamily $productFamily, SaveProductMasterService $service): JsonResponse
    {
        return $this->created(['product' => $this->variant($service->createVariant($productFamily, $request->validated())->load('capacityUnit'))]);
    }

    public function updateVariant(UpdateProductVariantRequest $request, ProductFamily $productFamily, Product $product, SaveProductMasterService $service): JsonResponse
    {
        abort_unless($product->product_family_id === $productFamily->id, 404);

        return $this->ok(['product' => $this->variant($service->updateVariant($product, $request->validated())->load('capacityUnit'))]);
    }

    public function export(Request $request): StreamedResponse
    {
        $validated = $this->validatedFilters($request, pagination: false);
        $fileName = 'products-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($validated): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['商品群コード', '商品群名', '商品区分', '商品コード', '商品表示名', '容量', '容量単位', 'アルコール分', '酒税区分', '消費税区分', '旧コード', '有効']);
            $this->filteredQuery($validated)->with(['products.capacityUnit', 'consumptionTaxCategory'])->orderBy('name')->chunk(100, function ($families) use ($handle): void {
                foreach ($families as $family) {
                    foreach ($family->products as $product) {
                        fputcsv($handle, [$family->family_code, $family->name, $family->product_type, $product->product_code,
                            $product->display_name, $product->capacity_value, $product->capacityUnit?->symbol,
                            $family->alcohol_percentage, $family->liquor_tax_category_code,
                            $family->consumptionTaxCategory?->name, $product->legacy_code, $product->is_active ? '有効' : '休止']);
                    }
                }
            });
            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function validatedFilters(Request $request, bool $pagination = true): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:255'], 'product_type' => ['nullable', 'in:sake,kasu,food,goods'],
            'active' => ['nullable', 'in:active,inactive,all'],
            'missing' => ['nullable', 'in:any,capacity,alcohol,tax,duplicate_capacity,unpasteurized_review'],
            'page' => $pagination ? ['nullable', 'integer', 'min:1'] : ['prohibited'],
            'per_page' => $pagination ? ['nullable', 'integer', 'min:10', 'max:100'] : ['prohibited'],
        ]);
    }

    private function filteredQuery(array $filters): Builder
    {
        $query = ProductFamily::query();
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where('name', 'ilike', $like)->orWhere('name_kana', 'ilike', $like)
                    ->orWhere('family_code', 'ilike', $like)->orWhere('brand_name', 'ilike', $like)
                    ->orWhereHas('products', fn (Builder $products) => $products->where('product_code', 'ilike', $like)
                        ->orWhere('display_name', 'ilike', $like)->orWhere('legacy_code', 'ilike', $like)
                        ->orWhere('legacy_name', 'ilike', $like));
            });
        }
        if (($filters['active'] ?? 'active') !== 'all') {
            $query->where('is_active', ($filters['active'] ?? 'active') === 'active');
        }
        $query->when($filters['product_type'] ?? null, fn (Builder $query, string $type) => $query->where('product_type', $type));
        $missing = $filters['missing'] ?? null;
        if ($missing === 'any') {
            $query->where(function (Builder $query): void {
                $query->whereNull('consumption_tax_category_id')->orWhereHas('products', fn (Builder $products) => $products->whereNull('capacity_value'))
                    ->orWhere(fn (Builder $sake) => $sake->where('product_type', 'sake')->where(fn (Builder $fields) => $fields->whereNull('alcohol_percentage')->orWhereNull('liquor_tax_category_code')));
            });
        } elseif ($missing === 'capacity') {
            $query->whereHas('products', fn (Builder $products) => $products->whereNull('capacity_value'));
        } elseif ($missing === 'alcohol') {
            $query->where('product_type', 'sake')->whereNull('alcohol_percentage');
        } elseif ($missing === 'tax') {
            $query->where(fn (Builder $query) => $query->whereNull('consumption_tax_category_id')->orWhere(fn (Builder $sake) => $sake->where('product_type', 'sake')->whereNull('liquor_tax_category_code')));
        } elseif ($missing === 'duplicate_capacity') {
            $query->whereHas('products', fn (Builder $products) => $products->whereExists(function ($duplicates): void {
                $duplicates->selectRaw('1')->from('products as duplicate_products')
                    ->whereColumn('duplicate_products.product_family_id', 'products.product_family_id')
                    ->whereColumn('duplicate_products.capacity_value', 'products.capacity_value')
                    ->whereColumn('duplicate_products.capacity_unit_id', 'products.capacity_unit_id')
                    ->whereColumn('duplicate_products.id', '!=', 'products.id');
            }));
        } elseif ($missing === 'unpasteurized_review') {
            $query->where('product_type', 'sake')->where('is_unpasteurized', false)
                ->where(fn (Builder $query) => $query->where('name', 'ilike', '%生%')->orWhere('name', 'ilike', '%なま%'));
        }

        return $query;
    }

    private function familySummary(ProductFamily $family): array
    {
        $products = $family->relationLoaded('products') ? $family->products : collect();

        return [
            'id' => $family->id, 'family_code' => $family->family_code, 'name' => $family->name,
            'product_type' => $family->product_type, 'is_unpasteurized' => $family->is_unpasteurized,
            'needs_unpasteurized_review' => $family->product_type === 'sake' && ! $family->is_unpasteurized
                && (str_contains($family->name, '生') || str_contains($family->name, 'なま')),
            'is_active' => $family->is_active, 'products_count' => $family->products_count ?? $products->count(),
            'capacity_labels' => $products->map(fn (Product $product): string => $product->variant_label ?: $this->capacityLabel($product))->filter()->unique()->values()->all(),
        ];
    }

    private function variant(Product $product): array
    {
        $usage = [
            'sales_orders' => $product->salesOrderLines()->count(),
            'shipments' => $product->shipmentLines()->count(),
            'invoices' => $product->invoiceLines()->count(),
            'prices' => $product->priceRules()->count(),
        ];

        return [
            'id' => $product->id, 'product_code' => $product->product_code, 'display_name' => $product->display_name,
            'variant_label' => $product->variant_label, 'capacity_value' => $product->capacity_value,
            'capacity_unit_id' => $product->capacity_unit_id,
            'capacity_unit_name' => $product->capacityUnit?->symbol ?: $product->capacityUnit?->name,
            'legacy_code' => $product->legacy_code, 'legacy_name' => $product->legacy_name,
            'is_active' => $product->is_active, 'is_package_locked' => array_sum($usage) > 0, 'usage_counts' => $usage,
        ];
    }

    private function capacityLabel(Product $product): string
    {
        if ($product->capacity_value === null) {
            return '';
        }

        return rtrim(rtrim(number_format((float) $product->capacity_value, 4, '.', ''), '0'), '.').($product->capacityUnit?->symbol ?: $product->capacityUnit?->name);
    }
}
