<?php

namespace App\Models\Retail;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RetailPurchaseOrder extends RetailModel
{
    protected $fillable = [
        'purchase_order_no',
        'retail_supplier_id',
        'supplier_type',
        'order_route',
        'brewery_sales_order_id',
        'brewery_cancellation_sales_order_id',
        'brewery_api_error',
        'status',
        'ordered_at',
        'cancelled_at',
        'cancelled_reason',
        'brewery_cancel_status',
        'brewery_cancel_error',
        'brewery_cancelled_at',
        'expected_delivery_date',
        'subtotal_amount',
        'tax_amount',
        'total_amount',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'ordered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'brewery_cancelled_at' => 'datetime',
            'expected_delivery_date' => 'date',
            'subtotal_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(RetailSupplier::class, 'retail_supplier_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RetailPurchaseOrderLine::class, 'retail_purchase_order_id');
    }
}
