<?php

namespace App\Models\Retail;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetailDeliveryLine extends RetailModel
{
    protected $fillable = [
        'retail_delivery_id',
        'retail_sale_item_id',
        'description',
        'quantity',
        'unit_price',
        'tax_rate',
        'tax_amount',
        'line_amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:2',
            'line_amount' => 'decimal:2',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(RetailDelivery::class, 'retail_delivery_id');
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(RetailSaleItem::class, 'retail_sale_item_id');
    }
}
