<?php

namespace App\Models\Retail;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RetailDelivery extends RetailModel
{
    protected $fillable = [
        'delivery_no',
        'retail_customer_id',
        'retail_sale_id',
        'delivery_date',
        'delivery_name',
        'delivery_postal_code',
        'delivery_address1',
        'delivery_address2',
        'billing_method_snapshot',
        'status',
        'issued_at',
        'cancelled_at',
        'cancellation_reason',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'delivery_date' => 'date',
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(RetailCustomer::class, 'retail_customer_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(RetailSale::class, 'retail_sale_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RetailDeliveryLine::class, 'retail_delivery_id');
    }
}
