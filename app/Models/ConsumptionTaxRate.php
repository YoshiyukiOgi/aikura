<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumptionTaxRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'consumption_tax_category_id',
        'name',
        'rate',
        'effective_from',
        'effective_to',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function consumptionTaxCategory(): BelongsTo
    {
        return $this->belongsTo(ConsumptionTaxCategory::class);
    }
}
