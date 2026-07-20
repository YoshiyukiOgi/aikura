<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'access_receivable_ledger_entry_id',
        'is_legacy_history',
        'status',
        'payment_date',
        'payment_method',
        'amount',
        'unapplied_amount',
        'reference_number',
        'note',
        'cancelled_at',
        'cancelled_reason',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'is_legacy_history' => 'boolean',
            'amount' => 'decimal:2',
            'unapplied_amount' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function accessReceivableLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(AccessReceivableLedgerEntry::class);
    }
}
