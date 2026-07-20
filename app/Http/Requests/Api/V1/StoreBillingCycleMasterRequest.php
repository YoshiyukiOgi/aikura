<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBillingCycleMasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return self::masterRules();
    }

    public static function masterRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'billing_method' => ['required', Rule::in(['monthly_closing', 'per_shipment', 'cash_immediate'])],
            'closing_day' => ['nullable', 'integer', 'between:1,31', 'required_if:billing_method,monthly_closing'],
            'payment_month_offset' => ['required', 'integer', 'between:0,12'],
            'payment_day' => ['nullable', 'integer', 'between:1,31'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
            'change_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
