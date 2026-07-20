<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_header_id',
        'shipment_header_id',
        'shipment_line_id',
        'source_invoice_line_id',
        'source_sales_return_line_id',
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
        ];
    }

    public function invoiceHeader(): BelongsTo
    {
        return $this->belongsTo(InvoiceHeader::class);
    }

    public function shipmentHeader(): BelongsTo
    {
        return $this->belongsTo(ShipmentHeader::class);
    }

    public function shipmentLine(): BelongsTo
    {
        return $this->belongsTo(ShipmentLine::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function consumptionTaxCategory(): BelongsTo
    {
        return $this->belongsTo(ConsumptionTaxCategory::class);
    }

    public function consumptionTaxRate(): BelongsTo
    {
        return $this->belongsTo(ConsumptionTaxRate::class);
    }

    public function sourceInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_invoice_line_id');
    }

    public function sourceSalesReturnLine(): BelongsTo
    {
        return $this->belongsTo(SalesReturnLine::class, 'source_sales_return_line_id');
    }
}
