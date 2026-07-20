<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnLineLot extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_return_line_id',
        'production_lot_id',
        'stock_location_id',
        'stock_movement_id',
        'quantity',
        'lot_code',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
        ];
    }

    public function salesReturnLine(): BelongsTo
    {
        return $this->belongsTo(SalesReturnLine::class);
    }

    public function productionLot(): BelongsTo
    {
        return $this->belongsTo(ProductionLot::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }
}
