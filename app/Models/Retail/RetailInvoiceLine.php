<?php

namespace App\Models\Retail;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetailInvoiceLine extends RetailModel
{
    protected $fillable = [
        'retail_invoice_id',
        'retail_sale_id',
        'description',
        'quantity',
        'unit_price',
        'tax_amount',
        'line_amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_amount' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(RetailInvoice::class, 'retail_invoice_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(RetailSale::class, 'retail_sale_id');
    }
}
