<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShipmentInstruction extends Model
{
    use HasFactory;

    protected $fillable = [
        'instruction_number',
        'status',
        'customer_id',
        'instruction_date',
        'scheduled_shipment_date',
        'stock_location_id',
        'cancelled_reason',
        'cancelled_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'instruction_date' => 'date',
            'scheduled_shipment_date' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ShipmentInstructionLine::class);
    }

    public function picks(): HasMany
    {
        return $this->hasMany(ShipmentPick::class);
    }
}
