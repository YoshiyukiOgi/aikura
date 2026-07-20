<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductFamilyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...self::familyRules(),
            'variants' => ['required', 'array', 'min:1', 'max:50'],
            'variants.*.product_code' => ['nullable', 'string', 'max:80', 'distinct', 'unique:products,product_code'],
            'variants.*.capacity_value' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'variants.*.capacity_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'variants.*.variant_label' => ['nullable', 'string', 'max:120'],
        ];
    }

    public static function familyRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'name_kana' => ['nullable', 'string', 'max:200'],
            'product_type' => ['required', Rule::in(['sake', 'kasu', 'food', 'goods'])],
            'brand_name' => ['nullable', 'string', 'max:120'],
            'category_name' => ['nullable', 'string', 'max:120'],
            'consumption_tax_category_id' => ['required', 'integer', 'exists:consumption_tax_categories,id'],
            'alcohol_percentage' => ['nullable', 'numeric', 'between:0,100', 'required_if:product_type,sake'],
            'liquor_tax_category_code' => ['nullable', 'string', 'max:80', 'required_if:product_type,sake'],
            'liquor_type_name' => ['nullable', 'string', 'max:120'],
            'ingredients' => ['nullable', 'string', 'max:2000'],
            'rice_polishing_ratio' => ['nullable', 'numeric', 'between:0,100'],
            'production_method' => ['nullable', 'string', 'max:120'],
            'is_unpasteurized' => ['required', 'boolean'],
            'is_sales_available' => ['required', 'boolean'],
            'is_inventory_managed' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:5000'],
            'change_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ((array) $this->input('variants', []) as $index => $variant) {
                $hasCapacity = filled($variant['capacity_value'] ?? null) && filled($variant['capacity_unit_id'] ?? null);
                if (($this->input('product_type') === 'sake' && ! $hasCapacity)
                    || (! $hasCapacity && ! filled($variant['variant_label'] ?? null))) {
                    $validator->errors()->add("variants.{$index}.capacity_value", '容量と単位、または規格表示を入力してください。');
                }
            }
        }];
    }
}
