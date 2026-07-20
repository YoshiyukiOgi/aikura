<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreShipmentPickRequest extends FormRequest
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
            'shipment_instruction_id' => ['required', 'integer', 'exists:shipment_instructions,id'],
            'pick_date' => ['required', 'date'],
            'stock_location_id' => ['nullable', 'integer', 'exists:stock_locations,id'],
            'note' => ['nullable', 'string', 'max:1000'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.shipment_instruction_line_id' => ['required', 'integer', 'exists:shipment_instruction_lines,id'],
            'lines.*.quantity' => ['required', 'regex:/^\d+(\.\d{1,4})?$/'],
            'lines.*.note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
