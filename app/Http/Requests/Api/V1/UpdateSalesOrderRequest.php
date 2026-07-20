<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'requested_shipment_date' => ['nullable', 'date'],
            'requested_delivery_date' => ['nullable', 'date'],
            'customer_order_number' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'work_note' => ['nullable', 'string'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['nullable', 'integer', 'exists:sales_order_lines,id'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'regex:/^\d+(\.\d{1,4})?$/'],
            'lines.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'lines.*.note' => ['nullable', 'string'],
        ];
    }
}
