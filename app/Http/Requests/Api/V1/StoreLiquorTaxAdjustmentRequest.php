<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreLiquorTaxAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'adjustment_type' => ['required', 'in:calculation_correction,return_correction,other'],
            'liquor_tax_category_id' => ['nullable', 'integer', 'exists:liquor_tax_categories,id'],
            'description' => ['required', 'string', 'max:1000'],
            'taxable_kl_adjustment' => ['required', 'numeric', 'between:-99999999.999999,99999999.999999'],
            'tax_amount_adjustment' => ['required', 'numeric', 'between:-999999999999.99,999999999999.99'],
        ];
    }
}
