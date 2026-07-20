<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invoice_header_id' => ['required', 'integer', 'exists:invoice_headers,id'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
