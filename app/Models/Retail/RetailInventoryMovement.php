<?php

namespace App\Models\Retail;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RetailInventoryMovement extends RetailModel
{
    protected $fillable = [
        'retail_product_id',
        'movement_type',
        'quantity',
        'stock_after',
        'source_type',
        'source_id',
        'occurred_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'stock_after' => 'decimal:3',
            'occurred_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(RetailProduct::class, 'retail_product_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
