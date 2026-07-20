<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsumptionTaxCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'taxability',
        'requires_tax_rate',
        'is_reduced_rate',
        'is_export_exempt',
        'is_invoice_display_target',
        'sort_order',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_tax_rate' => 'boolean',
            'is_reduced_rate' => 'boolean',
            'is_export_exempt' => 'boolean',
            'is_invoice_display_target' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ConsumptionTaxRate::class);
    }
}
