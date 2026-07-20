<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningReceivableBalance extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'as_of_date' => 'date',
            'source_sales_count' => 'integer',
            'source_ledger_entry_count' => 'integer',
            'source_sales_amount' => 'decimal:2',
            'source_ledger_amount' => 'decimal:2',
            'calculated_balance_amount' => 'decimal:2',
            'statement_balance_amount' => 'decimal:2',
            'adjustment_amount' => 'decimal:2',
            'opening_balance_amount' => 'decimal:2',
            'calculated_at' => 'datetime',
            'reconciled_at' => 'datetime',
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
}
