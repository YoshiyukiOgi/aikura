<?php

namespace App\Http\Requests\Api\V1;

class UpdateProductVariantRequest extends StoreProductVariantRequest
{
    public function rules(): array
    {
        return [
            'product_code' => ['prohibited'],
            'capacity_value' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'capacity_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'variant_label' => ['nullable', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
            'change_reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
