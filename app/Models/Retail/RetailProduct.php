<?php

namespace App\Models\Retail;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RetailProduct extends RetailModel
{
    protected $fillable = [
        'product_code',
        'name',
        'name_kana',
        'procurement_source',
        'retail_supplier_id',
        'brewery_product_id',
        'brewery_source_status',
        'brewery_source_checked_at',
        'cost_price',
        'selling_price',
        'tax_rate',
        'stock_unit',
        'reorder_point',
        'reorder_quantity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'reorder_point' => 'decimal:3',
            'reorder_quantity' => 'decimal:3',
            'is_active' => 'boolean',
            'brewery_source_checked_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(RetailSupplier::class, 'retail_supplier_id');
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(RetailSaleItem::class, 'retail_product_id');
    }

    public function inventoryStock(): HasOne
    {
        return $this->hasOne(RetailInventoryStock::class, 'retail_product_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(RetailInventoryMovement::class, 'retail_product_id');
    }
}
