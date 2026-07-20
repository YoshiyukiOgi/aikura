<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsumptionTaxMonthlyFiling extends Model
{
    use HasFactory;

    protected $fillable = [
        'status',
        'year',
        'month',
        'period_start',
        'period_end',
        'total_taxable_amount',
        'total_tax_amount',
        'total_confirmed_tax_amount',
        'total_amount',
        'invoice_count',
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
            'period_start' => 'date',
            'period_end' => 'date',
            'total_taxable_amount' => 'decimal:2',
            'total_tax_amount' => 'decimal:2',
            'total_confirmed_tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'invoice_count' => 'integer',
            'line_count' => 'integer',
            'calculated_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ConsumptionTaxMonthlyFilingLine::class);
    }
}
