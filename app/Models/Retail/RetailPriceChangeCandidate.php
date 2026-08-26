<?php

namespace App\Models\Retail;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetailPriceChangeCandidate extends RetailModel
{
    protected $fillable = [
        'retail_product_id',
        'brewery_product_id',
        'current_cost_price',
        'source_cost_price',
        'current_selling_price',
        'source_selling_price',
        'status',
        'detected_at',
        'applied_at',
        'applied_mode',
    ];

    protected function casts(): array
    {
        return [
            'current_cost_price' => 'decimal:2',
            'source_cost_price' => 'decimal:2',
            'current_selling_price' => 'decimal:2',
            'source_selling_price' => 'decimal:2',
            'detected_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(RetailProduct::class, 'retail_product_id');
    }
}
