<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiquorTaxMonthlyFilingLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'liquor_tax_monthly_filing_id',
        'line_no',
        'liquor_tax_category_id',
        'liquor_tax_category_code',
        'liquor_tax_category_name',
        'liquor_taxability',
        'liquor_tax_rule_id',
        'tax_treatment',
        'source_type',
        'calculation_method',
        'tax_per_kl',
        'reduction_rate',
        'taxable_kl',
        'estimated_amount',
        'gross_tax_amount',
        'relief_eligible_kl',
        'relief_amount',
        'deduction_amount',
        'net_tax_amount',
        'cumulative_gross_before',
        'cumulative_gross_after',
        'relief_calculation_basis',
        'requires_review',
        'confirmed_amount',
        'shipment_count',
        'line_count',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'tax_per_kl' => 'decimal:4',
            'reduction_rate' => 'decimal:4',
            'taxable_kl' => 'decimal:6',
            'estimated_amount' => 'decimal:2',
            'gross_tax_amount' => 'decimal:2',
            'relief_eligible_kl' => 'decimal:6',
            'relief_amount' => 'decimal:2',
            'deduction_amount' => 'decimal:2',
            'net_tax_amount' => 'decimal:2',
            'cumulative_gross_before' => 'decimal:2',
            'cumulative_gross_after' => 'decimal:2',
            'relief_calculation_basis' => 'array',
            'requires_review' => 'boolean',
            'confirmed_amount' => 'decimal:2',
            'shipment_count' => 'integer',
            'line_count' => 'integer',
        ];
    }

    public function filing(): BelongsTo
    {
        return $this->belongsTo(LiquorTaxMonthlyFiling::class, 'liquor_tax_monthly_filing_id');
    }

    public function liquorTaxCategory(): BelongsTo
    {
        return $this->belongsTo(LiquorTaxCategory::class);
    }

    public function liquorTaxRule(): BelongsTo
    {
        return $this->belongsTo(LiquorTaxRule::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(LiquorTaxMonthlyFilingSource::class);
    }
}
