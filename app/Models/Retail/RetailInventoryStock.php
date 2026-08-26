<?php

namespace App\Models\Retail;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetailInventoryStock extends RetailModel
{
    protected $fillable = [
        'retail_product_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(RetailProduct::class, 'retail_product_id');
    }
}
