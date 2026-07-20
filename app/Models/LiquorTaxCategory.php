<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiquorTaxCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'taxability',
        'aggregate0',
        'aggregate1',
        'aggregate2',
        'aggregate3',
        'print_order',
        'reduction_rate',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'print_order' => 'integer',
            'reduction_rate' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function rules(): HasMany
    {
        return $this->hasMany(LiquorTaxRule::class);
    }
}
