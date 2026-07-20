<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_header_id',
        'customer_id',
        'status',
        'expected_payment_date',
        'scheduled_amount',
        'received_amount',
        'outstanding_amount',
        'closed_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'expected_payment_date' => 'date',
            'scheduled_amount' => 'decimal:2',
            'received_amount' => 'decimal:2',
            'outstanding_amount' => 'decimal:2',
            'closed_at' => 'datetime',
        ];
    }

    public function invoiceHeader(): BelongsTo
    {
        return $this->belongsTo(InvoiceHeader::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
