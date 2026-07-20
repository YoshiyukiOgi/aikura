<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreShipmentRequest extends FormRequest
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
            'source_shipment_pick_id' => ['nullable', 'integer', 'exists:shipment_picks,id'],
            'customer_id' => ['required_without:source_shipment_pick_id', 'integer', 'exists:customers,id'],
            'document_date' => ['required_without:source_shipment_pick_id', 'date'],
            'order_date' => ['nullable', 'date'],
            'scheduled_shipment_date' => ['nullable', 'date'],
            'billing_target_date' => ['nullable', 'date'],
            'liquor_tax_transfer_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required_without:source_shipment_pick_id', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'regex:/^\d+(\.\d{1,4})?$/'],
            'lines.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'lines.*.note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
