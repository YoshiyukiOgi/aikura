<?php

namespace App\Models\Retail;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetailPaymentAllocation extends RetailModel
{
    protected $fillable = [
        'retail_payment_id',
        'retail_invoice_id',
        'allocated_amount',
    ];

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:2',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(RetailPayment::class, 'retail_payment_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(RetailInvoice::class, 'retail_invoice_id');
    }
}
