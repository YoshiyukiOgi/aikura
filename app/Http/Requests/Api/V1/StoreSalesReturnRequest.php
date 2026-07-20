<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesReturnRequest extends FormRequest
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
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'return_date' => ['required', 'date'],
            'settlement_method' => ['nullable', 'string', 'max:40'],
            'reason' => ['required', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.source_invoice_line_id' => ['required', 'integer', 'exists:invoice_lines,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.stock_action' => ['nullable', 'string', 'in:return_stock,return_dedicated_stock,non_sales_stock_operation,no_stock'],
            'lines.*.liquor_tax_return_treatment' => ['nullable', 'string', 'in:eligible,not_eligible,review'],
            'lines.*.liquor_tax_return_reason' => ['nullable', 'required_unless:lines.*.liquor_tax_return_treatment,review', 'string', 'max:1000'],
            'lines.*.stock_location_id' => ['nullable', 'integer', 'exists:stock_locations,id'],
            'lines.*.production_lot_id' => ['nullable', 'integer', 'exists:production_lots,id'],
            'lines.*.lot_code' => ['nullable', 'string', 'max:80'],
            'lines.*.reason' => ['nullable', 'string', 'max:1000'],
            'lines.*.note' => ['nullable', 'string', 'max:1000'],
            'lines.*.lots' => ['nullable', 'array'],
            'lines.*.lots.*.production_lot_id' => ['required_with:lines.*.lots', 'integer', 'exists:production_lots,id'],
            'lines.*.lots.*.quantity' => ['required_with:lines.*.lots', 'numeric', 'gt:0'],
            'lines.*.lots.*.stock_location_id' => ['nullable', 'integer', 'exists:stock_locations,id'],
            'lines.*.lots.*.lot_code' => ['nullable', 'string', 'max:80'],
            'lines.*.lots.*.note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
