<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreShipmentInstructionRequest extends FormRequest
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
            'instruction_date' => ['required', 'date'],
            'scheduled_shipment_date' => ['nullable', 'date'],
            'stock_location_id' => ['nullable', 'integer', 'exists:stock_locations,id'],
            'note' => ['nullable', 'string'],
            'reason' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sales_order_line_id' => ['required', 'integer', 'exists:sales_order_lines,id'],
            'lines.*.quantity' => ['required', 'regex:/^\d+(\.\d{1,4})?$/'],
            'lines.*.note' => ['nullable', 'string'],
        ];
    }
}
