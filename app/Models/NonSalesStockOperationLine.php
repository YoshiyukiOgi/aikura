<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NonSalesStockOperationLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'non_sales_stock_operation_header_id',
        'source_sales_return_line_id',
        'stock_movement_id',
        'line_no',
        'product_id',
        'stock_location_id',
        'unit_id',
        'quantity',
        'production_lot_id',
        'lot_code',
        'reason',
        'note',
        'liquor_tax_category_id',
        'liquor_tax_category_code',
        'liquor_tax_category_name',
        'liquor_taxability',
        'liquor_tax_rule_id',
        'liquor_tax_calculation_method',
        'liquor_taxable_kl',
        'liquor_tax_per_kl',
        'liquor_tax_reduction_rate',
        'liquor_tax_estimated_amount',
        'legacy_access_stock_leg_key',
        'legacy_access_detail_id',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'quantity' => 'decimal:4',
            'liquor_taxable_kl' => 'decimal:6',
            'liquor_tax_per_kl' => 'decimal:4',
            'liquor_tax_reduction_rate' => 'decimal:4',
            'liquor_tax_estimated_amount' => 'decimal:2',
        ];
    }

    public function header(): BelongsTo
    {
        return $this->belongsTo(NonSalesStockOperationHeader::class, 'non_sales_stock_operation_header_id');
    }

    public function sourceSalesReturnLine(): BelongsTo
    {
        return $this->belongsTo(SalesReturnLine::class, 'source_sales_return_line_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    public function productionLot(): BelongsTo
    {
        return $this->belongsTo(ProductionLot::class);
    }

    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class);
    }
}
