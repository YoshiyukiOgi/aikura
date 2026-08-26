<?php

namespace App\Models\Retail;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetailAdjustmentEvent extends RetailModel
{
    protected $fillable = [
        'retail_sale_id',
        'related_retail_sale_id',
        'event_type',
        'brewery_sync_status',
        'brewery_sales_order_id',
        'quantity_delta',
        'amount_delta',
        'reason',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'decimal:3',
            'amount_delta' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(RetailSale::class, 'retail_sale_id');
    }

    public function relatedSale(): BelongsTo
    {
        return $this->belongsTo(RetailSale::class, 'related_retail_sale_id');
    }
}
