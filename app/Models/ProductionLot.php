<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionLot extends Model
{
    use HasFactory;

    protected $fillable = [
        'lot_code',
        'display_name',
        'status',
        'stock_location_id',
        'unit_id',
        'capacity_value',
        'capacity_unit_id',
        'alcohol_percentage',
        'sake_meter_value',
        'acidity',
        'amino_acidity',
        'analysis_date',
        'analysis_status',
        'production_date',
        'bottling_date',
        'best_before_date',
        'tank_code',
        'rice_variety',
        'rice_polishing_ratio',
        'production_method',
        'storage_condition',
        'external_system_code',
        'legacy_lot_text',
        'search_key',
        'note',
        'is_active',
        'disabled_at',
    ];

    protected function casts(): array
    {
        return [
            'production_date' => 'date',
            'bottling_date' => 'date',
            'best_before_date' => 'date',
            'rice_polishing_ratio' => 'decimal:2',
            'capacity_value' => 'decimal:4',
            'alcohol_percentage' => 'decimal:2',
            'sake_meter_value' => 'decimal:2',
            'acidity' => 'decimal:2',
            'amino_acidity' => 'decimal:2',
            'analysis_date' => 'date',
            'is_active' => 'boolean',
            'disabled_at' => 'datetime',
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

    public function capacityUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'capacity_unit_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function shipmentLotAllocations(): HasMany
    {
        return $this->hasMany(ShipmentLotAllocation::class);
    }

}
