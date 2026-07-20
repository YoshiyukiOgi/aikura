<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivableMonthlyBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'status',
        'year',
        'month',
        'period_start',
        'period_end',
        'customer_id',
        'customer_code',
        'customer_name',
        'scheduled_amount',
        'received_amount',
        'outstanding_amount',
        'open_schedule_count',
        'partial_schedule_count',
        'closed_schedule_count',
        'calculated_at',
        'confirmed_at',
        'closed_at',
        'reason',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'scheduled_amount' => 'decimal:2',
            'received_amount' => 'decimal:2',
            'outstanding_amount' => 'decimal:2',
            'open_schedule_count' => 'integer',
            'partial_schedule_count' => 'integer',
            'closed_schedule_count' => 'integer',
            'calculated_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
