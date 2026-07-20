<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AllocateInventoryLotRequest extends FormRequest
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
            'shipment_line_id' => ['required', 'integer', 'exists:shipment_lines,id'],
            'production_lot_id' => ['required', 'integer', 'exists:production_lots,id'],
            'stock_location_id' => ['required', 'integer', 'exists:stock_locations,id'],
            'quantity' => ['required', 'regex:/^\d+(\.\d{1,4})?$/'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
