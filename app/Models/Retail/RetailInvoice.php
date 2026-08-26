<?php

namespace App\Models\Retail;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RetailInvoice extends RetailModel
{
    protected $fillable = [
        'invoice_no',
        'retail_customer_id',
        'invoice_date',
        'closing_date',
        'due_date',
        'subtotal_amount',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'balance_amount',
        'status',
        'cancelled_at',
        'cancel_reason',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'closing_date' => 'date',
            'due_date' => 'date',
            'subtotal_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(RetailCustomer::class, 'retail_customer_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RetailInvoiceLine::class, 'retail_invoice_id');
    }
}
