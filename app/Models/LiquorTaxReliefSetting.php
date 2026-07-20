<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiquorTaxReliefSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'manufacturing_site_code', 'scheme', 'effective_from', 'effective_to',
        'legacy_reduction_rate', 'legacy_annual_quantity_limit_kl',
        'opening_eligible_quantity_kl', 'opening_gross_tax_amount',
        'prior_year_total_taxable_quantity_kl', 'prior_year_peak_taxable_quantity_kl',
        'approval_date', 'approval_reference', 'selection_notice_date',
        'discontinuance_notice_date', 'calculation_rule_version', 'note', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date', 'effective_to' => 'date',
            'legacy_reduction_rate' => 'decimal:4',
            'legacy_annual_quantity_limit_kl' => 'decimal:6',
            'opening_eligible_quantity_kl' => 'decimal:6',
            'opening_gross_tax_amount' => 'decimal:2',
            'prior_year_total_taxable_quantity_kl' => 'decimal:6',
            'prior_year_peak_taxable_quantity_kl' => 'decimal:6',
            'approval_date' => 'date', 'selection_notice_date' => 'date', 'discontinuance_notice_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function filings(): HasMany
    {
        return $this->hasMany(LiquorTaxMonthlyFiling::class);
    }
}
