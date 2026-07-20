<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class RegisterPaymentRequest extends FormRequest
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
            'payment_schedule_id' => ['nullable', 'required_without:customer_id', 'integer', 'exists:payment_schedules,id'],
            'customer_id' => ['nullable', 'required_without:payment_schedule_id', 'integer', 'exists:customers,id'],
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
