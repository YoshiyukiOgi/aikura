<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShipmentInstructionLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_instruction_id',
        'line_no',
        'sales_order_id',
        'sales_order_line_id',
        'product_id',
        'quantity',
        'picked_quantity',
        'unit_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'quantity' => 'decimal:4',
            'picked_quantity' => 'decimal:4',
        ];
    }

    public function shipmentInstruction(): BelongsTo
    {
        return $this->belongsTo(ShipmentInstruction::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function shipmentPickLines(): HasMany
    {
        return $this->hasMany(ShipmentPickLine::class);
    }
}
