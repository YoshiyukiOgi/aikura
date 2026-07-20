<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesReturnHeader extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_number',
        'status',
        'customer_id',
        'return_date',
        'settlement_method',
        'credit_invoice_header_id',
        'credited_at',
        'cancelled_at',
        'cancelled_reason',
        'reason',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'credited_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creditInvoiceHeader(): BelongsTo
    {
        return $this->belongsTo(InvoiceHeader::class, 'credit_invoice_header_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesReturnLine::class);
    }
}
