<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCountLine extends Model
{
    protected $fillable = ['inventory_count_header_id', 'line_no', 'stock_location_id', 'unit_id', 'production_lot_id', 'lot_code', 'book_quantity', 'counted_quantity', 'variance_quantity', 'adjustment_stock_movement_id', 'counted_at', 'reason', 'note'];

    protected function casts(): array
    {
        return ['line_no' => 'integer', 'book_quantity' => 'decimal:4', 'counted_quantity' => 'decimal:4', 'variance_quantity' => 'decimal:4', 'counted_at' => 'datetime'];
    }

    public function header(): BelongsTo { return $this->belongsTo(InventoryCountHeader::class, 'inventory_count_header_id'); }
    public function stockLocation(): BelongsTo { return $this->belongsTo(StockLocation::class); }
    public function unit(): BelongsTo { return $this->belongsTo(Unit::class); }
    public function productionLot(): BelongsTo { return $this->belongsTo(ProductionLot::class); }
    public function adjustmentStockMovement(): BelongsTo { return $this->belongsTo(StockMovement::class, 'adjustment_stock_movement_id'); }
}
