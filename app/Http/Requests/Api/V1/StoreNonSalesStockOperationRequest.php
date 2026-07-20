<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNonSalesStockOperationRequest extends FormRequest
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
            'operation_type' => ['required', 'string', 'in:return_to_manufacturing,bottling,breakage,repackaging,disposal,loss,adjustment,self_consumption,gift,sample,inspection'],
            'consumption_tax_treatment' => ['prohibited'],
            'liquor_tax_treatment' => ['prohibited'],
            'operation_date' => ['required', 'date'],
            'source_sales_return_header_id' => ['nullable', 'integer', 'exists:sales_return_headers,id'],
            'reason' => [
                Rule::requiredIf(fn (): bool => ! $this->isMethod('post') || ! in_array($this->input('operation_type'), ['bottling', 'repackaging'], true)),
                'nullable',
                'string',
                'max:1000',
            ],
            'note' => ['nullable', 'string', 'max:1000'],
            'alcohol_warning_acknowledged' => ['nullable', 'boolean'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'lines.*.stock_location_id' => ['required', 'integer', 'exists:stock_locations,id'],
            'lines.*.unit_id' => ['prohibited'],
            'lines.*.quantity' => ['required', 'numeric', 'not_in:0'],
            'lines.*.production_lot_id' => ['required', 'integer', 'exists:production_lots,id'],
            'lines.*.source_sales_return_line_id' => ['nullable', 'integer', 'exists:sales_return_lines,id'],
            'lines.*.lot_code' => ['nullable', 'string', 'max:80'],
            'lines.*.reason' => ['nullable', 'string', 'max:1000'],
            'lines.*.note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
