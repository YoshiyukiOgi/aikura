<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'status',
        'movement_type',
        'movement_date',
        'product_id',
        'stock_location_id',
        'unit_id',
        'quantity',
        'source_type',
        'source_document_number',
        'source_line_no',
        'source_shipment_header_id',
        'source_shipment_line_id',
        'related_stock_movement_id',
        'production_lot_id',
        'lot_code',
        'confirmed_at',
        'closed_at',
        'cancelled_at',
        'cancelled_reason',
        'reason',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'movement_date' => 'date',
            'quantity' => 'decimal:4',
            'source_line_no' => 'integer',
            'confirmed_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function sourceShipmentHeader(): BelongsTo
    {
        return $this->belongsTo(ShipmentHeader::class, 'source_shipment_header_id');
    }

    public function sourceShipmentLine(): BelongsTo
    {
        return $this->belongsTo(ShipmentLine::class, 'source_shipment_line_id');
    }

    public function relatedStockMovement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_stock_movement_id');
    }

    public function productionLot(): BelongsTo
    {
        return $this->belongsTo(ProductionLot::class);
    }

    public function relatedStockMovements(): HasMany
    {
        return $this->hasMany(self::class, 'related_stock_movement_id');
    }
}
