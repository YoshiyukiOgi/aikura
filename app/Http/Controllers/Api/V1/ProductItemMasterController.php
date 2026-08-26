<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreProductItemRequest;
use App\Http\Requests\Api\V1\StoreProductPriceRevisionRequest;
use App\Models\AuditLog;
use App\Models\PriceList;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\TransactionCategory;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use App\Services\Masters\SaveProductItemMasterService;
use App\Services\Pricing\CreatePriceReviewTasksService;
use App\Support\SearchTextNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductItemMasterController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $this->filters($request);
        $paginator = $this->query($validated)
            ->with('capacityUnit:id,name,symbol')
            ->orderBy('name')->orderBy('capacity_value')->orderBy('id')
            ->paginate($validated['per_page'] ?? 30);

        return $this->ok([
            'products' => collect($paginator->items())->map(fn (Product $product): array => $this->summary($product))->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load([
            'baseUnit:id,code,name,symbol', 'salesUnit:id,code,name,symbol',
            'inventoryUnit:id,code,name,symbol', 'capacityUnit:id,code,name,symbol',
            'consumptionTaxCategory:id,code,name,is_reduced_rate',
            'consumptionTaxCategory.rates:id,consumption_tax_category_id,rate,effective_from,effective_to,is_active',
            'sakeDetail', 'kasuDetail', 'foodDetail', 'goodsDetail',
            'priceRules.priceList:id,code,name,price_type',
            'priceRules.transactionCategory:id,code,name', 'priceRules.customer:id,customer_code,name',
            'priceRules.unit:id,code,name,symbol',
        ]);

        return $this->ok(['product' => $this->detail($product)]);
    }

    public function store(StoreProductItemRequest $request, SaveProductItemMasterService $service): JsonResponse
    {
        return $this->created(['product' => $this->detail($service->create($request->validated())->load($this->detailRelations()))]);
    }

    public function update(StoreProductItemRequest $request, Product $product, SaveProductItemMasterService $service): JsonResponse
    {
        return $this->ok(['product' => $this->detail($service->update($product, $request->validated())->load($this->detailRelations()))]);
    }

    public function storePriceRevision(
        StoreProductPriceRevisionRequest $request,
        Product $product,
        AuditLogService $auditLogService,
        CreatePriceReviewTasksService $createReviewTasks,
    ): JsonResponse {
        $values = $request->validated();
        $rule = DB::transaction(function () use ($request, $product, $values, $auditLogService, $createReviewTasks): PriceRule {
            $scope = $this->scopeQuery(PriceRule::query(), $product, $values);
            $start = CarbonImmutable::parse($values['effective_from']);
            $end = isset($values['effective_to']) ? CarbonImmutable::parse($values['effective_to']) : null;

            $futureOverlap = (clone $scope)
                ->where('is_active', true)
                ->whereDate('effective_from', '>', $start)
                ->when($end, fn ($query) => $query->whereDate('effective_from', '<=', $end))
                ->exists();
            if ($futureOverlap || ($end === null && (clone $scope)->where('is_active', true)->whereDate('effective_from', '>', $start)->exists())) {
                throw ValidationException::withMessages(['effective_from' => '指定期間には、すでに将来価格が登録されています。']);
            }

            $currentRules = (clone $scope)
                ->where('is_active', true)
                ->whereDate('effective_from', '<=', $start)
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $start))
                ->lockForUpdate()->get();
            foreach ($currentRules as $current) {
                $before = $current->only(['unit_price', 'effective_from', 'effective_to', 'is_active']);
                if ($current->effective_from->toDateString() === $start->toDateString()) {
                    $current->update(['is_active' => false, 'reason' => $values['reason']]);
                } else {
                    $current->update(['effective_to' => $start->subDay()->toDateString(), 'reason' => $values['reason']]);
                }
                $auditLogService->record(new AuditLogData(
                    event: 'price_rule.closed_by_revision', auditable: $current, beforeValues: $before,
                    afterValues: $current->only(['unit_price', 'effective_from', 'effective_to', 'is_active']), reason: $values['reason'],
                ));
            }

            $priceList = $this->priceListForType($values['price_type']);
            $transactionCategory = TransactionCategory::query()
                ->where('code', $values['price_type'])
                ->where('is_active', true)
                ->firstOrFail();
            $rule = PriceRule::query()->create([
                'price_list_id' => $priceList->id,
                'product_id' => $product->id,
                'customer_id' => null,
                'transaction_category_id' => $transactionCategory->id,
                'unit_id' => $values['unit_id'],
                'unit_price' => $values['unit_price'],
                'currency' => 'JPY',
                'priority' => 200,
                'effective_from' => $start->toDateString(),
                'effective_to' => $end?->toDateString(),
                'rounding_method' => 'round',
                'reason' => $values['reason'],
                'is_active' => true,
            ]);
            $auditLogService->record(new AuditLogData(
                event: 'price_rule.revised', auditable: $rule, afterValues: $rule->getAttributes(),
                reason: $values['reason'], user: $request->user(),
            ));
            $oldPrice = $currentRules->sortByDesc('effective_from')->first()?->unit_price;
            if ($oldPrice !== null) {
                $createReviewTasks->createForChangedRule(
                    changedRule: $rule, oldUnitPrice: (string) $oldPrice, newUnitPrice: (string) $rule->unit_price,
                    createdByUserId: $request->user()?->id, reason: $values['reason'],
                );
            }

            return $rule;
        });

        return $this->created(['price_rule' => $this->priceRule($rule->load(['priceList', 'transactionCategory', 'unit']))]);
    }

    public function deactivatePriceRule(Request $request, Product $product, PriceRule $priceRule, AuditLogService $auditLogService): JsonResponse
    {
        $priceRule->loadMissing('priceList');
        abort_unless(
            $priceRule->product_id === $product->id
            && $priceRule->customer_id === null
            && in_array($priceRule->priceList?->price_type, ['producer', 'wholesale', 'retail'], true),
            404,
        );
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $before = $priceRule->only(['is_active', 'effective_to']);
        $priceRule->update(['is_active' => false, 'reason' => $validated['reason']]);
        $auditLogService->record(new AuditLogData(
            event: 'price_rule.deactivated', auditable: $priceRule, beforeValues: $before,
            afterValues: $priceRule->only(['is_active', 'effective_to']), reason: $validated['reason'],
        ));

        return $this->ok(['price_rule' => $this->priceRule($priceRule->load(['priceList', 'transactionCategory', 'unit']))]);
    }

    public function export(Request $request): StreamedResponse
    {
        $validated = $this->filters($request, false);

        return response()->streamDownload(function () use ($validated): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['商品コード', '商品名', 'カナ', '容量', '容量単位', '商品分類', '状態']);
            $this->query($validated)->with('capacityUnit')->orderBy('id')->chunk(200, function ($products) use ($handle): void {
                foreach ($products as $product) {
                    fputcsv($handle, [
                        $product->product_code, $product->name, $product->name_kana, $product->capacity_value,
                        $product->capacityUnit?->symbol ?: $product->capacityUnit?->name,
                        $product->category_name, $product->is_active ? '有効' : '無効',
                    ]);
                }
            });
            fclose($handle);
        }, 'products-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request, bool $pagination = true): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'string', 'max:40'],
            'category' => ['nullable', 'string', 'max:120'],
            'active' => ['nullable', 'in:active,inactive,all'],
            'page' => $pagination ? ['nullable', 'integer', 'min:1'] : ['prohibited'],
            'per_page' => $pagination ? ['nullable', 'integer', 'min:10', 'max:100'] : ['prohibited'],
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function query(array $filters): Builder
    {
        $query = Product::query();
        $search = SearchTextNormalizer::normalize($filters['q'] ?? null);
        if ($search !== '') {
            $query->where('search_key_normalized', 'ilike', '%'.$search.'%');
        }
        $capacity = str_replace(',', '', SearchTextNormalizer::normalize($filters['capacity'] ?? null));
        if ($capacity !== '') {
            if (! is_numeric($capacity)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('capacity_value', (float) $capacity);
            }
        }
        $query->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('category_name', $category));
        if (($filters['active'] ?? 'active') !== 'all') {
            $query->where('is_active', ($filters['active'] ?? 'active') === 'active');
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private function summary(Product $product): array
    {
        return [
            'id' => $product->id, 'product_code' => $product->product_code, 'name' => $product->name,
            'display_name' => $product->display_name, 'capacity_value' => $product->capacity_value,
            'capacity_unit_name' => $product->capacityUnit?->symbol ?: $product->capacityUnit?->name,
            'category_name' => $product->category_name, 'is_active' => $product->is_active,
        ];
    }

    /** @return array<string, mixed> */
    private function detail(Product $product): array
    {
        $productPriceRules = $this->productPriceRules($product);
        $priceRuleIds = $productPriceRules->pluck('id')->map(fn ($id): string => (string) $id);
        $history = AuditLog::query()->with('user:id,name')
            ->where(fn ($query) => $query
                ->where(fn ($productLogs) => $productLogs->where('target_table', 'products')->where('target_id', (string) $product->id))
                ->orWhere(fn ($priceLogs) => $priceLogs->where('target_table', 'price_rules')->whereIn('target_id', $priceRuleIds)))
            ->latest('occurred_at')->latest('id')->limit(100)->get()
            ->map(fn (AuditLog $log): array => [
                'event' => $log->event, 'occurred_at' => $log->occurred_at?->toIso8601String(),
                'user_name' => $log->user?->name, 'before_values' => $log->before_values,
                'after_values' => $log->after_values, 'reason' => $log->reason,
            ])->all();

        return [
            ...$this->summary($product),
            'product_type' => $product->product_type, 'name_kana' => $product->name_kana,
            'consumption_tax_category_id' => $product->consumption_tax_category_id,
            'base_unit_id' => $product->base_unit_id, 'sales_unit_id' => $product->sales_unit_id,
            'inventory_unit_id' => $product->inventory_unit_id, 'capacity_unit_id' => $product->capacity_unit_id,
            'alcohol_percentage' => $product->alcohol_percentage, 'is_alcohol' => $product->is_alcohol,
            'is_sales_available' => $product->is_sales_available, 'is_inventory_managed' => $product->is_inventory_managed,
            'note' => $product->note,
            'liquor_tax_category_code' => $product->sakeDetail?->liquor_tax_category_code,
            'liquor_type_name' => $product->sakeDetail?->liquor_type_name,
            'ingredients' => $product->sakeDetail?->ingredients,
            'rice_polishing_ratio' => $product->sakeDetail?->rice_polishing_ratio,
            'production_method' => $product->sakeDetail?->production_method,
            'is_unpasteurized' => (bool) ($product->sakeDetail?->is_unpasteurized ?? false),
            'kasu_type' => $product->kasuDetail?->kasu_type, 'storage_method' => $product->kasuDetail?->storage_method ?? $product->foodDetail?->storage_method,
            'food_category' => $product->foodDetail?->food_category, 'allergen_note' => $product->foodDetail?->allergen_note,
            'shelf_life_days' => $product->foodDetail?->shelf_life_days,
            'goods_category' => $product->goodsDetail?->goods_category, 'material' => $product->goodsDetail?->material,
            'size_description' => $product->goodsDetail?->size_description,
            'price_rules' => $productPriceRules->sortByDesc('effective_from')->map(fn (PriceRule $rule): array => $this->priceRule($rule, $product))->values()->all(),
            'history' => $history,
        ];
    }

    /** @return array<string, mixed> */
    private function priceRule(PriceRule $rule, ?Product $product = null): array
    {
        $priceType = $rule->priceList?->price_type;

        return [
            'id' => $rule->id,
            'price_type' => $priceType,
            'price_type_name' => match ($priceType) {
                'producer' => '生産者価格',
                'wholesale' => '卸価格',
                'retail' => '小売価格（税抜）',
                default => $rule->priceList?->name,
            },
            'price_list_name' => $rule->priceList?->name,
            'unit_id' => $rule->unit_id, 'unit_name' => $rule->unit?->symbol ?: $rule->unit?->name,
            'unit_price' => $rule->unit_price, 'effective_from' => $rule->effective_from?->toDateString(),
            'effective_to' => $rule->effective_to?->toDateString(), 'is_active' => $rule->is_active,
            'reason' => $rule->reason,
            'tax_included_unit_price' => $priceType === 'retail' && $product !== null
                ? $this->taxIncludedPrice($product, (string) $rule->unit_price, $rule->effective_from?->toDateString())
                : null,
        ];
    }

    /** @param array<string, mixed> $values */
    private function scopeQuery(Builder $query, Product $product, array $values): Builder
    {
        $query->where('product_id', $product->id)->where('unit_id', $values['unit_id']);

        $priceList = $this->priceListForType($values['price_type']);

        return $query->whereNull('customer_id')->where('price_list_id', $priceList->id);
    }

    private function priceListForType(string $priceType): PriceList
    {
        return PriceList::query()
            ->where('code', $priceType.'_price')
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function productPriceRules(Product $product)
    {
        return $product->priceRules->filter(fn (PriceRule $rule): bool => $rule->customer_id === null
            && in_array($rule->priceList?->price_type, ['producer', 'wholesale', 'retail'], true)
        );
    }

    private function taxIncludedPrice(Product $product, string $unitPrice, ?string $effectiveFrom): string
    {
        $date = $effectiveFrom ?? now()->toDateString();
        $rate = $product->consumptionTaxCategory?->rates
            ?->filter(fn ($rate): bool => $rate->is_active
                && $rate->effective_from?->toDateString() <= $date
                && ($rate->effective_to === null || $rate->effective_to->toDateString() >= $date))
            ->sortByDesc('effective_from')
            ->first()
            ?->rate ?? '0';

        return number_format(round((float) bcmul($unitPrice, bcadd('1', (string) $rate, 4), 4)), 4, '.', '');
    }

    /** @return array<int, string> */
    private function detailRelations(): array
    {
        return [
            'baseUnit', 'salesUnit', 'inventoryUnit', 'capacityUnit', 'consumptionTaxCategory.rates',
            'sakeDetail', 'kasuDetail', 'foodDetail', 'goodsDetail',
            'priceRules.priceList', 'priceRules.transactionCategory', 'priceRules.customer', 'priceRules.unit',
        ];
    }
}
