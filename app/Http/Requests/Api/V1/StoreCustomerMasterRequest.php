<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerMasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_code' => ['required', 'string', 'max:80', 'unique:customers,customer_code'],
            ...$this->customerRules(),
        ];
    }

    public static function customerRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'name_kana' => ['nullable', 'string', 'max:160'],
            'short_name' => ['nullable', 'string', 'max:120'],
            'billing_name' => ['nullable', 'string', 'max:160'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'address1' => ['nullable', 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'fax' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'transaction_category_id' => ['required', 'integer', 'exists:transaction_categories,id'],
            'settlement_receivable_category_id' => ['required', 'integer', 'exists:settlement_receivable_categories,id'],
            'billing_cycle_id' => ['required', 'integer', 'exists:billing_cycles,id'],
            'tax_rounding_method' => ['required', Rule::in(['round', 'floor', 'ceil'])],
            'tax_calculation_unit' => ['required', Rule::in(['line', 'invoice'])],
            'amount_rounding_method' => ['required', Rule::in(['round', 'floor', 'ceil'])],
            'invoice_required' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['required', 'boolean'],
            'change_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
