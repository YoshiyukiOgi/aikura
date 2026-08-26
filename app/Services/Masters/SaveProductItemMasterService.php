<?php

namespace App\Services\Masters;

use App\Models\FoodProductDetail;
use App\Models\GoodsProductDetail;
use App\Models\KasuProductDetail;
use App\Models\Product;
use App\Models\SakeProductDetail;
use App\Services\Audit\AuditLogService;
use App\Support\SearchTextNormalizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveProductItemMasterService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /** @param array<string, mixed> $values */
    public function create(array $values): Product
    {
        return DB::transaction(function () use ($values): Product {
            $product = Product::query()->create($this->productAttributes($values));
            $this->syncDetails($product, $values);
            $this->auditLogService->recordModelChange(
                'product.created',
                $product,
                afterValues: $product->getAttributes(),
                reason: $values['change_reason'] ?? '商品登録',
            );

            return $product->refresh();
        });
    }

    /** @param array<string, mixed> $values */
    public function update(Product $product, array $values): Product
    {
        return DB::transaction(function () use ($product, $values): Product {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $before = $product->getAttributes();
            $product->update($this->productAttributes($values, $product));
            $this->syncDetails($product, $values);
            $this->auditLogService->recordModelChange(
                'product.updated',
                $product,
                beforeValues: $before,
                afterValues: $product->getAttributes(),
                reason: $values['change_reason'],
            );

            return $product->refresh();
        });
    }

    /** @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function productAttributes(array $values, ?Product $product = null): array
    {
        $attributes = Arr::only($values, [
            'product_code', 'product_type', 'name', 'name_kana', 'display_name', 'category_name',
            'consumption_tax_category_id', 'base_unit_id', 'sales_unit_id', 'inventory_unit_id',
            'capacity_value', 'capacity_unit_id', 'alcohol_percentage', 'is_sales_available',
            'is_inventory_managed', 'is_active', 'note',
        ]);
        $attributes['is_alcohol'] = $values['product_type'] === 'sake';
        $attributes['disabled_at'] = $values['is_active'] ? null : ($product?->disabled_at ?? now());
        $attributes['search_key'] = implode(' ', array_filter([
            $values['product_code'], $values['name'], $values['name_kana'] ?? null, $values['display_name'],
        ]));
        $attributes['search_key_normalized'] = SearchTextNormalizer::productKey(
            $values['product_code'],
            $values['name'],
            $values['name_kana'] ?? '',
            $values['display_name'],
        );

        return $attributes;
    }

    /** @param array<string, mixed> $values */
    private function syncDetails(Product $product, array $values): void
    {
        if ($product->product_type === 'sake') {
            SakeProductDetail::query()->updateOrCreate(['product_id' => $product->id], Arr::only($values, [
                'liquor_tax_category_code', 'liquor_type_name', 'ingredients', 'rice_polishing_ratio',
                'production_method', 'is_unpasteurized',
            ]));
        }
        if ($product->product_type === 'kasu') {
            KasuProductDetail::query()->updateOrCreate(['product_id' => $product->id], Arr::only($values, ['kasu_type', 'storage_method']));
        }
        if ($product->product_type === 'food') {
            FoodProductDetail::query()->updateOrCreate(['product_id' => $product->id], Arr::only($values, [
                'food_category', 'allergen_note', 'storage_method', 'shelf_life_days',
            ]));
        }
        if ($product->product_type === 'goods') {
            GoodsProductDetail::query()->updateOrCreate(['product_id' => $product->id], Arr::only($values, [
                'goods_category', 'material', 'size_description',
            ]));
        }
    }
}
