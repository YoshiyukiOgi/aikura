<?php

namespace App\Services\Masters;

use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Unit;
use App\Services\Audit\AuditLogService;
use App\Support\SearchTextNormalizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveProductMasterService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function createFamily(array $values): ProductFamily
    {
        return DB::transaction(function () use ($values): ProductFamily {
            $family = ProductFamily::query()->create([
                'family_code' => 'custom_'.Str::lower((string) Str::ulid()),
                ...$this->familyAttributes($values),
            ]);
            foreach ($values['variants'] as $variant) {
                $this->createVariantRecord($family, $variant);
            }
            $this->auditLogService->recordModelChange(
                'product_family.created', $family, afterValues: $family->getAttributes(),
                reason: $values['change_reason'] ?? '商品群新規登録',
            );
            $this->clearCaches();

            return $family->refresh();
        });
    }

    public function updateFamily(ProductFamily $family, array $values): ProductFamily
    {
        return DB::transaction(function () use ($family, $values): ProductFamily {
            if ($family->product_type !== $values['product_type']) {
                throw ValidationException::withMessages([
                    'product_type' => '登録後の商品区分は変更できません。別の商品群として登録してください。',
                ]);
            }
            $before = $family->getAttributes();
            $family->fill($this->familyAttributes($values))->save();
            foreach ($family->products()->get() as $product) {
                $product->fill($this->productSharedAttributes($family));
                $product->display_name = $this->displayName($family, $product->variant_label, $product->capacity_value, $product->capacityUnit);
                $product->search_key = $this->productSearchKey($family, $product);
                $product->save();
                $this->syncSakeDetail($family, $product);
            }
            $this->auditLogService->recordModelChange(
                'product_family.updated', $family, $before, $family->getAttributes(), $values['change_reason'],
            );
            $this->clearCaches();

            return $family->refresh();
        });
    }

    public function createVariant(ProductFamily $family, array $values): Product
    {
        return DB::transaction(function () use ($family, $values): Product {
            $product = $this->createVariantRecord($family, $values);
            $this->auditLogService->recordModelChange(
                'product_variant.created', $product, afterValues: $product->getAttributes(),
                reason: $values['change_reason'] ?? '容量・規格追加',
            );
            $this->clearCaches();

            return $product;
        });
    }

    public function updateVariant(Product $product, array $values): Product
    {
        return DB::transaction(function () use ($product, $values): Product {
            $product->loadMissing(['productFamily', 'capacityUnit']);
            $before = $product->getAttributes();
            $oldAutomaticLabel = $this->capacityLabel($product->capacity_value, $product->capacityUnit);
            $packageChanged = (string) $product->capacity_value !== (string) ($values['capacity_value'] ?? '')
                || (int) $product->capacity_unit_id !== (int) ($values['capacity_unit_id'] ?? 0);
            if ($packageChanged && $this->hasUsage($product)) {
                throw ValidationException::withMessages([
                    'capacity_value' => '取引・在庫履歴がある商品の容量は変更できません。新しい容量を追加してください。',
                ]);
            }
            $product->capacity_value = $values['capacity_value'] ?? null;
            $product->capacity_unit_id = $values['capacity_unit_id'] ?? null;
            $submittedLabel = $this->blankToNull($values['variant_label'] ?? null);
            $product->variant_label = ($submittedLabel === null || $submittedLabel === $oldAutomaticLabel)
                ? $this->capacityLabel($product->capacity_value, $product->capacityUnit()->first())
                : $submittedLabel;
            $product->is_active = $values['is_active'];
            $product->disabled_at = $values['is_active'] ? null : now();
            $product->display_name = $this->displayName($product->productFamily, $product->variant_label, $product->capacity_value, $product->capacityUnit()->first());
            $product->search_key = $this->productSearchKey($product->productFamily, $product);
            $product->save();
            $this->auditLogService->recordModelChange(
                'product_variant.updated', $product, $before, $product->getAttributes(), $values['change_reason'],
            );
            $this->clearCaches();

            return $product->refresh();
        });
    }

    private function createVariantRecord(ProductFamily $family, array $values): Product
    {
        $unit = isset($values['capacity_unit_id']) ? Unit::query()->find($values['capacity_unit_id']) : null;
        $variantLabel = $this->blankToNull($values['variant_label'] ?? null)
            ?? $this->capacityLabel($values['capacity_value'] ?? null, $unit);
        $displayName = $this->displayName($family, $variantLabel, $values['capacity_value'] ?? null, $unit);
        $baseUnit = Unit::query()->where('code', match ($family->product_type) {
            'sake' => 'bottle', 'kasu' => 'bag', default => 'piece',
        })->firstOrFail();
        $product = Product::query()->create([
            'product_code' => $this->blankToNull($values['product_code'] ?? null) ?? 'SYS-P-'.Str::upper(Str::substr((string) Str::ulid(), -12)),
            'product_family_id' => $family->id,
            ...$this->productSharedAttributes($family),
            'display_name' => $displayName,
            'variant_label' => $variantLabel,
            'base_unit_id' => $baseUnit->id,
            'sales_unit_id' => $baseUnit->id,
            'inventory_unit_id' => $baseUnit->id,
            'capacity_value' => $values['capacity_value'] ?? null,
            'capacity_unit_id' => $values['capacity_unit_id'] ?? null,
            'search_key' => '',
            'is_active' => $values['is_active'] ?? true,
            'disabled_at' => ($values['is_active'] ?? true) ? null : now(),
        ]);
        $product->search_key = $this->productSearchKey($family, $product);
        $product->save();
        $this->syncSakeDetail($family, $product);

        return $product->refresh();
    }

    private function familyAttributes(array $values): array
    {
        $fields = ['name', 'name_kana', 'product_type', 'brand_name', 'category_name', 'consumption_tax_category_id',
            'alcohol_percentage', 'liquor_tax_category_code', 'liquor_type_name', 'ingredients', 'rice_polishing_ratio',
            'production_method', 'is_unpasteurized', 'is_sales_available', 'is_inventory_managed', 'note', 'is_active'];
        $attributes = Arr::only($values, $fields);
        foreach (['name_kana', 'brand_name', 'category_name', 'liquor_tax_category_code', 'liquor_type_name', 'ingredients', 'production_method', 'note'] as $field) {
            $attributes[$field] = $this->blankToNull($attributes[$field] ?? null);
        }
        if ($attributes['product_type'] !== 'sake') {
            foreach (['alcohol_percentage', 'liquor_tax_category_code', 'liquor_type_name', 'ingredients', 'rice_polishing_ratio', 'production_method'] as $field) {
                $attributes[$field] = null;
            }
            $attributes['is_unpasteurized'] = false;
        }
        $attributes['is_alcohol'] = $attributes['product_type'] === 'sake';
        $attributes['disabled_at'] = $attributes['is_active'] ? null : now();
        $attributes['search_key'] = SearchTextNormalizer::searchKey(
            $attributes['name'] ?? null,
            $attributes['name_kana'] ?? null,
            $attributes['brand_name'] ?? null,
            $attributes['category_name'] ?? null,
            $attributes['liquor_type_name'] ?? null,
            $attributes['product_code'] ?? null,
        );

        return $attributes;
    }

    private function productSharedAttributes(ProductFamily $family): array
    {
        return [
            'product_type' => $family->product_type, 'name' => $family->name, 'name_kana' => $family->name_kana,
            'brand_name' => $family->brand_name, 'category_name' => $family->category_name,
            'consumption_tax_category_id' => $family->consumption_tax_category_id,
            'alcohol_percentage' => $family->alcohol_percentage, 'is_alcohol' => $family->is_alcohol,
            'is_sales_available' => $family->is_sales_available, 'is_inventory_managed' => $family->is_inventory_managed,
        ];
    }

    private function syncSakeDetail(ProductFamily $family, Product $product): void
    {
        if ($family->product_type !== 'sake') {
            return;
        }
        $product->sakeDetail()->updateOrCreate([], [
            'liquor_tax_category_code' => $family->liquor_tax_category_code,
            'liquor_type_name' => $family->liquor_type_name,
            'ingredients' => $family->ingredients,
            'rice_polishing_ratio' => $family->rice_polishing_ratio,
            'production_method' => $family->production_method,
            'is_unpasteurized' => $family->is_unpasteurized,
        ]);
    }

    private function displayName(ProductFamily $family, ?string $variantLabel, mixed $capacity, ?Unit $unit): string
    {
        $label = $this->blankToNull($variantLabel) ?? $this->capacityLabel($capacity, $unit);
        $name = trim($family->name.' '.($label ?? ''));
        if (mb_strlen($name) > 160) {
            throw ValidationException::withMessages(['name' => '商品群名と容量・規格を合わせた名称は160文字以内にしてください。']);
        }

        return $name;
    }

    private function capacityLabel(mixed $value, ?Unit $unit): ?string
    {
        if ($value === null || $value === '' || $unit === null) {
            return null;
        }

        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.').($unit->symbol ?: $unit->name);
    }

    private function productSearchKey(ProductFamily $family, Product $product): string
    {
        return SearchTextNormalizer::searchKey(
            $family->search_key,
            $product->display_name,
            $product->series_name,
            $product->style_name,
            $product->legacy_code,
            $product->legacy_name,
            $product->variant_label,
            $product->product_code,
        );
    }

    private function hasUsage(Product $product): bool
    {
        return DB::table('sales_order_lines')->where('product_id', $product->id)->exists()
            || DB::table('shipment_lines')->where('product_id', $product->id)->exists()
            || DB::table('invoice_lines')->where('product_id', $product->id)->exists()
            || DB::table('price_rules')->where('product_id', $product->id)->exists();
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function clearCaches(): void
    {
        Cache::forget('sales_order_page.products');
    }
}
