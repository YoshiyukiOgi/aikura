<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLiquorTaxAdjustmentSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'manufacturing_site_code' => ['sometimes', 'string', 'max:80'],
            'approval_amount_threshold' => ['required', 'numeric', 'between:0,999999999999.99'],
            'approval_quantity_threshold_kl' => ['required', 'numeric', 'between:0,99999999.999999'],
            'is_active' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
