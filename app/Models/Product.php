<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_code',
        'product_family_id',
        'product_type',
        'name',
        'name_kana',
        'display_name',
        'variant_label',
        'brand_name',
        'series_name',
        'style_name',
        'category_name',
        'consumption_tax_category_id',
        'base_unit_id',
        'sales_unit_id',
        'inventory_unit_id',
        'capacity_value',
        'capacity_unit_id',
        'alcohol_percentage',
        'is_alcohol',
        'is_sales_available',
        'is_inventory_managed',
        'search_key',
        'legacy_code',
        'legacy_name',
        'note',
        'is_active',
        'disabled_at',
    ];

    protected function casts(): array
    {
        return [
            'capacity_value' => 'decimal:4',
            'alcohol_percentage' => 'decimal:2',
            'is_alcohol' => 'boolean',
            'is_sales_available' => 'boolean',
            'is_inventory_managed' => 'boolean',
            'is_active' => 'boolean',
            'disabled_at' => 'datetime',
        ];
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function productFamily(): BelongsTo
    {
        return $this->belongsTo(ProductFamily::class);
    }

    public function salesUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'sales_unit_id');
    }

    public function inventoryUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'inventory_unit_id');
    }

    public function capacityUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'capacity_unit_id');
    }

    public function consumptionTaxCategory(): BelongsTo
    {
        return $this->belongsTo(ConsumptionTaxCategory::class);
    }

    public function unitConversions(): HasMany
    {
        return $this->hasMany(UnitConversion::class);
    }

    public function priceRules(): HasMany
    {
        return $this->hasMany(PriceRule::class);
    }

    public function salesOrderLines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class);
    }

    public function shipmentLines(): HasMany
    {
        return $this->hasMany(ShipmentLine::class);
    }

    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function sakeDetail(): HasOne
    {
        return $this->hasOne(SakeProductDetail::class);
    }

    public function kasuDetail(): HasOne
    {
        return $this->hasOne(KasuProductDetail::class);
    }

    public function foodDetail(): HasOne
    {
        return $this->hasOne(FoodProductDetail::class);
    }

    public function goodsDetail(): HasOne
    {
        return $this->hasOne(GoodsProductDetail::class);
    }
}
