<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ShipmentLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_header_id',
        'line_no',
        'product_id',
        'quantity',
        'unit_id',
        'source_shipment_pick_line_id',
        'shipment_instruction_line_id',
        'draft_unit_price',
        'draft_price_list_id',
        'draft_price_rule_id',
        'draft_price_source',
        'draft_price_reason',
        'draft_priced_at',
        'confirmed_product_code',
        'confirmed_product_name',
        'confirmed_display_name',
        'confirmed_product_type',
        'confirmed_unit_code',
        'confirmed_unit_name',
        'confirmed_quantity',
        'confirmed_unit_price',
        'confirmed_price_list_id',
        'confirmed_price_rule_id',
        'confirmed_price_source',
        'confirmed_price_reason',
        'confirmed_capacity_value',
        'confirmed_capacity_unit_id',
        'confirmed_alcohol_percentage',
        'confirmed_rounding_method',
        'confirmed_consumption_tax_category_id',
        'confirmed_consumption_tax_category_code',
        'confirmed_consumption_tax_category_name',
        'confirmed_consumption_taxability',
        'confirmed_consumption_tax_rate_id',
        'confirmed_consumption_tax_rate',
        'confirmed_consumption_tax_rate_effective_from',
        'confirmed_liquor_tax_category_id',
        'confirmed_liquor_tax_category_code',
        'confirmed_liquor_tax_category_name',
        'confirmed_liquor_taxability',
        'confirmed_liquor_tax_rule_id',
        'confirmed_liquor_tax_calculation_method',
        'confirmed_liquor_taxable_kl',
        'confirmed_liquor_tax_per_kl',
        'confirmed_liquor_tax_reduction_rate',
        'confirmed_liquor_tax_estimated_amount',
        'confirmed_at',
        'note',
        'legacy_access_line_id',
        'legacy_access_detail_id',
        'legacy_access_transaction_amount',
        'legacy_access_consumption_tax_amount',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'quantity' => 'decimal:4',
            'draft_unit_price' => 'decimal:4',
            'draft_priced_at' => 'datetime',
            'confirmed_quantity' => 'decimal:4',
            'confirmed_unit_price' => 'decimal:4',
            'confirmed_capacity_value' => 'decimal:4',
            'confirmed_alcohol_percentage' => 'decimal:2',
            'confirmed_consumption_tax_rate' => 'decimal:4',
            'confirmed_consumption_tax_rate_effective_from' => 'date',
            'confirmed_liquor_taxable_kl' => 'decimal:6',
            'confirmed_liquor_tax_per_kl' => 'decimal:4',
            'confirmed_liquor_tax_reduction_rate' => 'decimal:4',
            'confirmed_liquor_tax_estimated_amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'legacy_access_transaction_amount' => 'decimal:2',
            'legacy_access_consumption_tax_amount' => 'decimal:2',
        ];
    }

    public function shipmentHeader(): BelongsTo
    {
        return $this->belongsTo(ShipmentHeader::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function sourceShipmentPickLine(): BelongsTo
    {
        return $this->belongsTo(ShipmentPickLine::class, 'source_shipment_pick_line_id');
    }

    public function shipmentInstructionLine(): BelongsTo
    {
        return $this->belongsTo(ShipmentInstructionLine::class);
    }

    public function draftPriceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class, 'draft_price_list_id');
    }

    public function draftPriceRule(): BelongsTo
    {
        return $this->belongsTo(PriceRule::class, 'draft_price_rule_id');
    }

    public function confirmedPriceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class, 'confirmed_price_list_id');
    }

    public function confirmedPriceRule(): BelongsTo
    {
        return $this->belongsTo(PriceRule::class, 'confirmed_price_rule_id');
    }

    public function confirmedCapacityUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'confirmed_capacity_unit_id');
    }

    public function confirmedConsumptionTaxCategory(): BelongsTo
    {
        return $this->belongsTo(ConsumptionTaxCategory::class, 'confirmed_consumption_tax_category_id');
    }

    public function confirmedConsumptionTaxRate(): BelongsTo
    {
        return $this->belongsTo(ConsumptionTaxRate::class, 'confirmed_consumption_tax_rate_id');
    }

    public function confirmedLiquorTaxCategory(): BelongsTo
    {
        return $this->belongsTo(LiquorTaxCategory::class, 'confirmed_liquor_tax_category_id');
    }

    public function confirmedLiquorTaxRule(): BelongsTo
    {
        return $this->belongsTo(LiquorTaxRule::class, 'confirmed_liquor_tax_rule_id');
    }

    public function invoiceLine(): HasOne
    {
        return $this->hasOne(InvoiceLine::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'source_shipment_line_id');
    }

    public function lotAllocations(): HasMany
    {
        return $this->hasMany(ShipmentLotAllocation::class);
    }
}
