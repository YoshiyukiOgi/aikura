<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_code' => ['nullable', 'string', 'max:80', 'unique:products,product_code'],
            'capacity_value' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'capacity_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'variant_label' => ['nullable', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
            'change_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $hasCapacity = filled($this->input('capacity_value')) && filled($this->input('capacity_unit_id'));
            if (($this->route('productFamily')?->product_type === 'sake' && ! $hasCapacity)
                || (! $hasCapacity && ! filled($this->input('variant_label')))) {
                $validator->errors()->add('capacity_value', '容量と単位、または規格表示を入力してください。');
            }
        }];
    }
}
