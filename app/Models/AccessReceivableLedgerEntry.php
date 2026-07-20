<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AccessReceivableLedgerEntry extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'billing_year' => 'integer',
            'billing_month' => 'integer',
            'signed_amount' => 'decimal:2',
            'is_previous_month_bill' => 'boolean',
            'is_transfer_fee' => 'boolean',
            'source_payload' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AccessMigrationBatch::class, 'access_migration_batch_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}
