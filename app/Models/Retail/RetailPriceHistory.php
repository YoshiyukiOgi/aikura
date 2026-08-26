<?php

namespace App\Models\Retail;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetailPriceHistory extends RetailModel
{
    protected $fillable = [
        'retail_product_id',
        'retail_price_change_candidate_id',
        'old_cost_price',
        'new_cost_price',
        'old_selling_price',
        'new_selling_price',
        'apply_mode',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'old_cost_price' => 'decimal:2',
            'new_cost_price' => 'decimal:2',
            'old_selling_price' => 'decimal:2',
            'new_selling_price' => 'decimal:2',
            'applied_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(RetailProduct::class, 'retail_product_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(RetailPriceChangeCandidate::class, 'retail_price_change_candidate_id');
    }
}
