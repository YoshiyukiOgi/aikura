<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalMonthlyBalance extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'year' => 'integer', 'month' => 'integer', 'period_start' => 'date', 'period_end' => 'date',
            'opening_amount' => 'decimal:2', 'charge_amount' => 'decimal:2', 'settlement_amount' => 'decimal:2', 'closing_amount' => 'decimal:2',
            'calculated_at' => 'datetime', 'confirmed_at' => 'datetime', 'closed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
