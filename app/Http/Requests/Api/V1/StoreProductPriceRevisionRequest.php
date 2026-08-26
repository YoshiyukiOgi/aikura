<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductPriceRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'price_type' => ['required', Rule::in(['producer', 'wholesale', 'retail'])],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'unit_price' => ['required', 'regex:/^\d+(\.\d{1,4})?$/'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
