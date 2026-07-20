<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ShipmentPickLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_pick_id',
        'line_no',
        'shipment_instruction_line_id',
        'product_id',
        'quantity',
        'unit_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'quantity' => 'decimal:4',
        ];
    }

    public function shipmentPick(): BelongsTo
    {
        return $this->belongsTo(ShipmentPick::class);
    }

    public function shipmentInstructionLine(): BelongsTo
    {
        return $this->belongsTo(ShipmentInstructionLine::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function shipmentLine(): HasOne
    {
        return $this->hasOne(ShipmentLine::class, 'source_shipment_pick_line_id');
    }

    public function lotAllocations(): HasMany
    {
        return $this->hasMany(ShipmentLotAllocation::class);
    }
}
