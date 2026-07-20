<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LiquorTaxMonthlyFiling extends Model
{
    use HasFactory;

    protected $fillable = [
        'status',
        'year',
        'month',
        'period_start',
        'period_end',
        'manufacturing_site_code',
        'fiscal_year',
        'liquor_tax_relief_setting_id',
        'relief_scheme',
        'calculation_rule_version',
        'total_taxable_kl',
        'total_gross_tax_amount',
        'total_relief_amount',
        'total_deduction_amount',
        'net_payable_amount',
        'total_adjustment_taxable_kl',
        'total_adjustment_amount',
        'adjustment_count',
        'warning_count',
        'total_estimated_amount',
        'total_confirmed_amount',
        'shipment_count',
        'line_count',
        'calculated_at',
        'confirmed_at',
        'closed_at',
        'reason',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'fiscal_year' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'total_taxable_kl' => 'decimal:6',
            'total_gross_tax_amount' => 'decimal:2',
            'total_relief_amount' => 'decimal:2',
            'total_deduction_amount' => 'decimal:2',
            'net_payable_amount' => 'decimal:2',
            'total_adjustment_taxable_kl' => 'decimal:6',
            'total_adjustment_amount' => 'decimal:2',
            'adjustment_count' => 'integer',
            'warning_count' => 'integer',
            'total_estimated_amount' => 'decimal:2',
            'total_confirmed_amount' => 'decimal:2',
            'shipment_count' => 'integer',
            'line_count' => 'integer',
            'calculated_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(LiquorTaxMonthlyFilingLine::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(LiquorTaxMonthlyFilingSource::class);
    }

    public function reliefSetting(): BelongsTo
    {
        return $this->belongsTo(LiquorTaxReliefSetting::class, 'liquor_tax_relief_setting_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(LiquorTaxMonthlyFilingAdjustment::class);
    }

    public function reportExports(): MorphMany
    {
        return $this->morphMany(ReportExport::class, 'exportable');
    }
}
