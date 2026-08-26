<?php

namespace App\Models\Retail;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RetailSaleItem extends RetailModel
{
    protected $fillable = [
        'retail_sale_id',
        'retail_product_id',
        'brewery_sales_order_line_id',
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

    public function sale(): BelongsTo
    {
        return $this->belongsTo(RetailSale::class, 'retail_sale_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(RetailProduct::class, 'retail_product_id');
    }

    public function invoiceLine(): HasOne
    {
        return $this->hasOne(RetailInvoiceLine::class, 'retail_sale_id', 'retail_sale_id');
    }
}
