<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesReturnLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_return_header_id',
        'source_invoice_line_id',
        'source_shipment_header_id',
        'source_shipment_line_id',
        'credit_invoice_line_id',
        'line_no',
        'product_id',
        'product_code',
        'product_name',
        'display_name',
        'quantity',
        'unit_code',
        'unit_name',
        'unit_price',
        'amount',
        'consumption_tax_category_id',
        'consumption_tax_category_code',
        'consumption_tax_category_name',
        'consumption_taxability',
        'consumption_tax_rate_id',
        'tax_rate',
        'consumption_tax_rate_effective_from',
        'tax_amount',
        'total_amount',
        'stock_action',
        'stock_location_id',
        'production_lot_id',
        'lot_code',
        'stock_movement_id',
        'liquor_tax_return_treatment',
        'liquor_tax_return_reason',
        'liquor_tax_reviewed_by',
        'liquor_tax_reviewed_at',
        'reason',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'amount' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'consumption_tax_rate_effective_from' => 'date',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'liquor_tax_reviewed_at' => 'datetime',
        ];
    }

    public function salesReturnHeader(): BelongsTo
    {
        return $this->belongsTo(SalesReturnHeader::class);
    }

    public function sourceInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class, 'source_invoice_line_id');
    }

    public function sourceShipmentLine(): BelongsTo
    {
        return $this->belongsTo(ShipmentLine::class, 'source_shipment_line_id');
    }

    public function creditInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class, 'credit_invoice_line_id');
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(SalesReturnLineLot::class);
    }

    public function liquorTaxReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'liquor_tax_reviewed_by');
    }
}
