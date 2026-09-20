<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalBalanceOpening extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['as_of_date' => 'date', 'opening_balance_amount' => 'decimal:2'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
