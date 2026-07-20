<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class OverrideSalesOrderLinePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'unit_price' => ['required', 'regex:/^\d+(\.\d{1,4})?$/'],
            'reason' => ['required', 'string', 'max:1000'],
            'save_as_customer_price' => ['sometimes', 'boolean'],
        ];
    }
}
