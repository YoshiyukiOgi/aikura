<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerMasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...StoreCustomerMasterRequest::customerRules(),
            'change_reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
