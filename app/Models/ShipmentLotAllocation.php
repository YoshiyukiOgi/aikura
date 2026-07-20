<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentLotAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'status',
        'shipment_header_id',
        'shipment_line_id',
        'shipment_pick_line_id',
        'product_id',
        'production_lot_id',
        'stock_location_id',
        'unit_id',
        'quantity',
        'standard_alcohol_percentage',
        'actual_alcohol_percentage',
        'allowed_alcohol_min',
        'allowed_alcohol_max',
        'alcohol_compliance_status',
        'approval_request_id',
        'liquor_tax_category_id',
        'liquor_tax_rule_id',
        'liquor_taxable_kl',
        'liquor_tax_per_kl',
        'liquor_tax_estimated_amount',
        'allocated_at',
        'confirmed_at',
        'cancelled_at',
        'cancelled_reason',
        'reason',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'standard_alcohol_percentage' => 'decimal:2',
            'actual_alcohol_percentage' => 'decimal:2',
            'allowed_alcohol_min' => 'decimal:2',
            'allowed_alcohol_max' => 'decimal:2',
            'liquor_taxable_kl' => 'decimal:6',
            'liquor_tax_per_kl' => 'decimal:4',
            'liquor_tax_estimated_amount' => 'decimal:2',
            'allocated_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function shipmentHeader(): BelongsTo
    {
        return $this->belongsTo(ShipmentHeader::class);
    }

    public function shipmentLine(): BelongsTo
    {
        return $this->belongsTo(ShipmentLine::class);
    }

    public function shipmentPickLine(): BelongsTo
    {
        return $this->belongsTo(ShipmentPickLine::class);
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productionLot(): BelongsTo
    {
        return $this->belongsTo(ProductionLot::class);
    }

    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
