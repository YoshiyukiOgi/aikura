<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiquorTaxRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'liquor_tax_category_id',
        'code',
        'name',
        'calculation_method',
        'tax_per_kl',
        'alcohol_percentage_min',
        'alcohol_percentage_max',
        'base_alcohol_percentage',
        'additional_tax_per_kl_per_percent',
        'reduction_rate',
        'special_provision_code',
        'effective_from',
        'effective_to',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tax_per_kl' => 'decimal:4',
            'alcohol_percentage_min' => 'decimal:2',
            'alcohol_percentage_max' => 'decimal:2',
            'base_alcohol_percentage' => 'decimal:2',
            'additional_tax_per_kl_per_percent' => 'decimal:4',
            'reduction_rate' => 'decimal:4',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function liquorTaxCategory(): BelongsTo
    {
        return $this->belongsTo(LiquorTaxCategory::class);
    }
}
