<?php

namespace App\Models\Retail;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RetailSale extends RetailModel
{
    protected $fillable = [
        'retail_company_id',
        'sale_no',
        'retail_customer_id',
        'sale_date',
        'sale_type',
        'status',
        'original_retail_sale_id',
        'correction_type',
        'payment_status',
        'subtotal_amount',
        'tax_amount',
        'total_amount',
        'note',
        'correction_reason',
        'cancelled_at',
        'revised_at',
        'closed_at',
        'brewery_sales_order_id',
        'brewery_order_number',
        'brewery_correction_sales_order_id',
        'brewery_correction_order_number',
        'brewery_sync_status',
        'brewery_sync_error',
        'brewery_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'subtotal_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'cancelled_at' => 'datetime',
            'revised_at' => 'datetime',
            'closed_at' => 'datetime',
            'brewery_synced_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(RetailCustomer::class, 'retail_customer_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(RetailCompany::class, 'retail_company_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RetailSaleItem::class, 'retail_sale_id');
    }

    public function originalSale(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_retail_sale_id');
    }

    public function correctionSales(): HasMany
    {
        return $this->hasMany(self::class, 'original_retail_sale_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(RetailDelivery::class, 'retail_sale_id');
    }

    public function invoiceLines(): HasMany
    {
        return $this->hasMany(RetailInvoiceLine::class, 'retail_sale_id');
    }

    public function adjustmentEvents(): HasMany
    {
        return $this->hasMany(RetailAdjustmentEvent::class, 'retail_sale_id');
    }
}
