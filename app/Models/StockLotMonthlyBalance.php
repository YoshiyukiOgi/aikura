<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLotMonthlyBalance extends Model
{
    protected $fillable = ['status', 'year', 'month', 'period_start', 'period_end', 'production_lot_id', 'stock_location_id', 'unit_id', 'closing_quantity', 'calculated_at', 'confirmed_at'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'month' => 'integer', 'period_start' => 'date', 'period_end' => 'date', 'closing_quantity' => 'decimal:4', 'calculated_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }

    public function productionLot(): BelongsTo { return $this->belongsTo(ProductionLot::class); }
    public function stockLocation(): BelongsTo { return $this->belongsTo(StockLocation::class); }
    public function unit(): BelongsTo { return $this->belongsTo(Unit::class); }
}
