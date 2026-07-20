<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ShipmentPick extends Model
{
    use HasFactory;

    protected $fillable = [
        'pick_number',
        'status',
        'shipment_instruction_id',
        'pick_date',
        'stock_location_id',
        'cancelled_reason',
        'cancelled_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'pick_date' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    public function shipmentInstruction(): BelongsTo
    {
        return $this->belongsTo(ShipmentInstruction::class);
    }

    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ShipmentPickLine::class);
    }

    public function shipmentHeader(): HasOne
    {
        return $this->hasOne(ShipmentHeader::class, 'source_shipment_pick_id');
    }
}
