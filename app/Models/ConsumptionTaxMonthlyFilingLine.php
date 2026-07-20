<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumptionTaxMonthlyFilingLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'consumption_tax_monthly_filing_id',
        'line_no',
        'consumption_tax_category_id',
        'consumption_tax_category_code',
        'consumption_tax_category_name',
        'consumption_taxability',
        'consumption_tax_rate_id',
        'tax_rate',
        'consumption_tax_rate_effective_from',
        'taxable_amount',
        'tax_amount',
        'confirmed_tax_amount',
        'total_amount',
        'invoice_count',
        'line_count',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'tax_rate' => 'decimal:4',
            'consumption_tax_rate_effective_from' => 'date',
            'taxable_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'confirmed_tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'invoice_count' => 'integer',
            'line_count' => 'integer',
        ];
    }

    public function filing(): BelongsTo
    {
        return $this->belongsTo(ConsumptionTaxMonthlyFiling::class, 'consumption_tax_monthly_filing_id');
    }

    public function consumptionTaxCategory(): BelongsTo
    {
        return $this->belongsTo(ConsumptionTaxCategory::class);
    }

    public function consumptionTaxRate(): BelongsTo
    {
        return $this->belongsTo(ConsumptionTaxRate::class);
    }
}
