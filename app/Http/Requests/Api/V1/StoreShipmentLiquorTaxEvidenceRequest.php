<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreShipmentLiquorTaxEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:pending,confirmed,rejected'],
            'evidence_reference' => ['nullable', 'required_if:status,confirmed', 'string', 'max:160'],
            'evidence_date' => ['nullable', 'required_if:status,confirmed', 'date'],
            'destination' => ['nullable', 'required_if:status,confirmed', 'string', 'max:160'],
            'customs_office' => ['nullable', 'string', 'max:160'],
            'exporter_type' => ['nullable', 'in:direct,indirect'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,xls,xlsx,doc,docx', 'max:10240'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
