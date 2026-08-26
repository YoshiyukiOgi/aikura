<?php

namespace App\Models\Retail;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RetailPayment extends RetailModel
{
    protected $fillable = [
        'payment_no',
        'retail_customer_id',
        'payment_date',
        'payment_method',
        'status',
        'original_retail_payment_id',
        'adjustment_type',
        'amount',
        'unapplied_amount',
        'note',
        'adjustment_reason',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'unapplied_amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(RetailCustomer::class, 'retail_customer_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(RetailPaymentAllocation::class, 'retail_payment_id');
    }

    public function originalPayment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_retail_payment_id');
    }

    public function adjustmentPayments(): HasMany
    {
        return $this->hasMany(self::class, 'original_retail_payment_id');
    }
}
