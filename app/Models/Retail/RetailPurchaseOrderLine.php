<?php

namespace App\Models\Retail;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetailPurchaseOrderLine extends RetailModel
{
    protected $fillable = [
        'retail_purchase_order_id',
        'retail_product_id',
        'description',
        'quantity',
        'unit_cost',
        'tax_amount',
        'line_amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_amount' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(RetailPurchaseOrder::class, 'retail_purchase_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(RetailProduct::class, 'retail_product_id');
    }
}
