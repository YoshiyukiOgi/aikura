<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreLiquorTaxReliefSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'manufacturing_site_code' => ['sometimes', 'string', 'max:80'],
            'scheme' => ['required', 'in:legacy_scheme,new_scheme'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'legacy_reduction_rate' => ['nullable', 'numeric', 'between:0,1'],
            'legacy_annual_quantity_limit_kl' => ['nullable', 'numeric', 'between:0,999999999.999999'],
            'opening_eligible_quantity_kl' => ['nullable', 'numeric', 'between:0,999999999.999999'],
            'opening_gross_tax_amount' => ['nullable', 'numeric', 'between:0,9999999999999999.99'],
            'prior_year_total_taxable_quantity_kl' => ['nullable', 'numeric', 'between:0,999999999.999999'],
            'prior_year_peak_taxable_quantity_kl' => ['nullable', 'numeric', 'between:0,999999999.999999'],
            'approval_date' => ['nullable', 'date_format:Y-m-d'],
            'approval_reference' => ['nullable', 'string', 'max:160'],
            'selection_notice_date' => ['nullable', 'date_format:Y-m-d'],
            'discontinuance_notice_date' => ['nullable', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:1000'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
