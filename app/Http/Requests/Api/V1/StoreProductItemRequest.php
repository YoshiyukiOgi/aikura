<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'product_code' => ['required', 'string', 'max:80', Rule::unique('products', 'product_code')->ignore($this->route('product'))],
            'product_type' => ['required', Rule::in(['sake', 'kasu', 'food', 'goods'])],
            'name' => ['required', 'string', 'max:160'],
            'name_kana' => ['nullable', 'string', 'max:160'],
            'display_name' => ['required', 'string', 'max:160'],
            'category_name' => ['nullable', 'string', 'max:120'],
            'consumption_tax_category_id' => ['required', 'integer', 'exists:consumption_tax_categories,id'],
            'base_unit_id' => ['required', 'integer', 'exists:units,id'],
            'sales_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'inventory_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'capacity_value' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'capacity_unit_id' => ['nullable', 'integer', 'exists:units,id', 'required_with:capacity_value'],
            'alcohol_percentage' => ['nullable', 'numeric', 'between:0,100', 'required_if:product_type,sake'],
            'liquor_tax_category_code' => ['nullable', 'string', 'max:80', 'required_if:product_type,sake'],
            'liquor_type_name' => ['nullable', 'string', 'max:120'],
            'ingredients' => ['nullable', 'string', 'max:2000'],
            'rice_polishing_ratio' => ['nullable', 'numeric', 'between:0,100'],
            'production_method' => ['nullable', 'string', 'max:120'],
            'is_unpasteurized' => ['required', 'boolean'],
            'kasu_type' => ['nullable', 'string', 'max:120'],
            'food_category' => ['nullable', 'string', 'max:120'],
            'allergen_note' => ['nullable', 'string', 'max:2000'],
            'storage_method' => ['nullable', 'string', 'max:120'],
            'shelf_life_days' => ['nullable', 'integer', 'min:0', 'max:36500'],
            'goods_category' => ['nullable', 'string', 'max:120'],
            'material' => ['nullable', 'string', 'max:120'],
            'size_description' => ['nullable', 'string', 'max:120'],
            'is_sales_available' => ['required', 'boolean'],
            'is_inventory_managed' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:5000'],
            'change_reason' => [$this->route('product') ? 'required' : 'nullable', 'string', 'max:1000'],
        ];
    }
}
